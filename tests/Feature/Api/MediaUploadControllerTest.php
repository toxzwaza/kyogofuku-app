<?php

namespace Tests\Feature\Api;

use App\Models\DeviceRegistration;
use App\Models\MediaFile;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MediaUploadControllerTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;
    private DeviceRegistration $device;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('s3_public');

        $this->shop = Shop::create(['name' => '岡山店', 'is_active' => true]);

        $this->token = Str::random(64);
        $this->device = DeviceRegistration::create([
            'shop_id' => $this->shop->id,
            'device_code' => DeviceRegistration::generateUniqueDeviceCode(),
            'token_hash' => DeviceRegistration::hashToken($this->token),
            'label' => 'KI-IPAD-01',
            'last_used_at' => now(),
        ]);
    }

    private function upload(UploadedFile $file, ?string $token = null, array $extra = [])
    {
        return $this->withHeader('Authorization', 'Bearer ' . ($token ?? $this->token))
            ->post('/api/media/upload', array_merge(['image' => $file], $extra), ['Accept' => 'application/json']);
    }

    public function test_端末トークンで画像をアップロードするとメディアライブラリに登録される(): void
    {
        $file = UploadedFile::fake()->image('IMG_0001.jpg', 640, 480);

        $response = $this->upload($file);

        $response->assertStatus(201)
            ->assertJsonPath('duplicated', false)
            ->assertJsonStructure(['media' => ['id', 'url', 'width', 'height', 'sha256']]);

        $media = MediaFile::first();
        $this->assertNotNull($media);
        $this->assertSame($this->device->id, $media->device_registration_id);
        $this->assertSame('image/webp', $media->mime_type);
        $this->assertSame(640, $media->width);
        $this->assertSame(hash_file('sha256', $file->getRealPath()), $media->sha256);
        Storage::disk('s3_public')->assertExists($media->path);
    }

    public function test_同一ファイルの再送は重複登録されず既存レコードが返る(): void
    {
        $file = UploadedFile::fake()->image('IMG_0002.jpg', 320, 240);
        $sha256 = hash_file('sha256', $file->getRealPath());

        $this->upload($file)->assertStatus(201);

        // 同一内容のファイルを再送（リトライ想定）
        $again = UploadedFile::fake()->createWithContent('IMG_0002.jpg', file_get_contents($file->getRealPath()));
        $response = $this->upload($again);

        $response->assertStatus(200)->assertJsonPath('duplicated', true);
        $this->assertSame(1, MediaFile::count());
        $this->assertSame($sha256, MediaFile::first()->sha256);
    }

    public function test_last_used_atとlast_ipが更新される(): void
    {
        $this->device->forceFill(['last_used_at' => now()->subDay(), 'last_ip' => null])->save();

        $this->upload(UploadedFile::fake()->image('IMG_0003.jpg'))->assertStatus(201);

        $this->device->refresh();
        $this->assertTrue($this->device->last_used_at->isToday());
        $this->assertNotNull($this->device->last_ip);
    }

    public function test_クライアント申告のsha256が一致しない場合は422(): void
    {
        $response = $this->upload(
            UploadedFile::fake()->image('IMG_0004.jpg'),
            null,
            ['sha256' => str_repeat('a', 64)],
        );

        $response->assertStatus(422)->assertJsonPath('error', 'checksum_mismatch');
        $this->assertSame(0, MediaFile::count());
    }

    public function test_トークンなしは401(): void
    {
        $response = $this->post('/api/media/upload', [
            'image' => UploadedFile::fake()->image('IMG_0005.jpg'),
        ], ['Accept' => 'application/json']);

        $response->assertStatus(401);
    }

    public function test_失効済みトークンは401(): void
    {
        $this->device->forceFill(['revoked_at' => now()])->save();

        $this->upload(UploadedFile::fake()->image('IMG_0006.jpg'))->assertStatus(401);
        $this->assertSame(0, MediaFile::count());
    }

    public function test_画像以外のファイルは422(): void
    {
        $response = $this->upload(UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'));

        $response->assertStatus(422);
        $this->assertSame(0, MediaFile::count());
    }

    public function test_10MB超のファイルは422(): void
    {
        $response = $this->upload(UploadedFile::fake()->create('big.jpg', 11000, 'image/jpeg'));

        $response->assertStatus(422);
    }
}
