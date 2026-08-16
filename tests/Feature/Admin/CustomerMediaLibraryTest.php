<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\CustomerPhoto;
use App\Models\MediaFile;
use App\Models\MediaTag;
use App\Models\PhotoType;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * feature/customer-photo-media-library:
 * 顧客写真のメディアライブラリ選択（タブレット画像タグ・店舗プレフィックス絞込）
 */
class CustomerMediaLibraryTest extends TestCase
{
    use RefreshDatabase;

    private Shop $fukui;
    private Shop $okayama;
    private MediaTag $parentTag;
    private MediaTag $hirataTag;
    private MediaTag $kouichiTag;

    protected function setUp(): void
    {
        parent::setUp();
        $this->fukui = Shop::create(['name' => '福井店', 'is_active' => true]);
        $this->okayama = Shop::create(['name' => '岡山店', 'is_active' => true]);
        $this->parentTag = MediaTag::create(['name' => 'タブレット画像', 'parent_id' => null]);
        $this->hirataTag = MediaTag::create(['name' => 'HIRATA-IPAD-01', 'parent_id' => $this->parentTag->id]);
        $this->kouichiTag = MediaTag::create(['name' => 'KOUICHI-IPAD-01', 'parent_id' => $this->parentTag->id]);
    }

    private function userOf(Shop $shop): User
    {
        $user = User::factory()->create();
        $user->shops()->attach($shop->id, ['main' => 1]);

        return $user;
    }

    private function media(string $filename, MediaTag $tag, string $path = null): MediaFile
    {
        $media = MediaFile::create([
            'original_filename' => $filename,
            'path' => $path ?? 'media/'.$filename,
            'storage_disk' => 'public',
            'mime_type' => 'image/png',
            'file_size' => 100,
        ]);
        $media->mediaTags()->attach($tag->id);

        return $media;
    }

    public function test_fukui_user_sees_only_hirata_tags_and_images(): void
    {
        $this->media('hirata.png', $this->hirataTag);
        $this->media('kouichi.png', $this->kouichiTag);

        $res = $this->actingAs($this->userOf($this->fukui))
            ->getJson(route('admin.customers.media-library'))
            ->assertOk()
            ->assertJsonPath('prefix', 'HIRATA-')
            ->assertJsonPath('deviceTags.0.name', 'HIRATA-IPAD-01')
            ->assertJsonCount(1, 'deviceTags');

        $files = collect($res->json('mediaFiles.data'));
        $this->assertCount(1, $files);
        $this->assertSame('hirata.png', $files->first()['original_filename']);
    }

    public function test_other_shop_user_sees_only_kouichi_tags_and_images(): void
    {
        $this->media('hirata.png', $this->hirataTag);
        $this->media('kouichi.png', $this->kouichiTag);

        $res = $this->actingAs($this->userOf($this->okayama))
            ->getJson(route('admin.customers.media-library'))
            ->assertOk()
            ->assertJsonPath('prefix', 'KOUICHI-')
            ->assertJsonPath('deviceTags.0.name', 'KOUICHI-IPAD-01');

        $files = collect($res->json('mediaFiles.data'));
        $this->assertCount(1, $files);
        $this->assertSame('kouichi.png', $files->first()['original_filename']);
    }

    public function test_device_tag_filter_narrows_results(): void
    {
        $kouichi02 = MediaTag::create(['name' => 'KOUICHI-IPAD-02', 'parent_id' => $this->parentTag->id]);
        $this->media('ipad01.png', $this->kouichiTag);
        $this->media('ipad02.png', $kouichi02);

        $res = $this->actingAs($this->userOf($this->okayama))
            ->getJson(route('admin.customers.media-library', ['tag_id' => $kouichi02->id]))
            ->assertOk();

        $files = collect($res->json('mediaFiles.data'));
        $this->assertCount(1, $files);
        $this->assertSame('ipad02.png', $files->first()['original_filename']);
    }

    public function test_store_photo_from_media_copies_image_as_customer_photo(): void
    {
        Storage::fake('public');
        Storage::fake('s3_private');

        // 実画像を public ディスクに配置
        $img = imagecreatetruecolor(10, 10);
        ob_start();
        imagepng($img);
        $png = ob_get_clean();
        Storage::disk('public')->put('media/test.png', $png);

        $media = $this->media('test.png', $this->kouichiTag, 'media/test.png');
        $type = PhotoType::create(['name' => 'その他', 'code' => 'other', 'is_active' => true, 'sort_order' => 1]);
        $customer = Customer::create(['name' => '顧客', 'shop_id' => $this->okayama->id]);

        $this->actingAs($this->userOf($this->okayama))
            ->post(route('admin.customers.photos.from-media', $customer), [
                'media_file_id' => $media->id,
                'photo_type_id' => $type->id,
                'remarks' => 'メディアから',
            ])
            ->assertSessionHas('success');

        $photo = CustomerPhoto::where('customer_id', $customer->id)->first();
        $this->assertNotNull($photo);
        $this->assertSame('メディアから', $photo->remarks);
        $this->assertStringEndsWith('.webp', $photo->file_path);
        Storage::disk('s3_private')->assertExists($photo->file_path);
    }

    public function test_store_photo_from_media_rejects_other_shop_prefix(): void
    {
        Storage::fake('public');
        Storage::fake('s3_private');

        $media = $this->media('hirata.png', $this->hirataTag);
        $type = PhotoType::create(['name' => 'その他', 'code' => 'other', 'is_active' => true, 'sort_order' => 1]);
        $customer = Customer::create(['name' => '顧客', 'shop_id' => $this->okayama->id]);

        // 岡山店ユーザーが HIRATA- タグの画像IDを直接指定 → 拒否
        $this->actingAs($this->userOf($this->okayama))
            ->post(route('admin.customers.photos.from-media', $customer), [
                'media_file_id' => $media->id,
                'photo_type_id' => $type->id,
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseMissing('customer_photos', ['customer_id' => $customer->id]);
    }
}
