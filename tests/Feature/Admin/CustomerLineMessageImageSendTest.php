<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\CustomerLineContact;
use App\Models\CustomerLineMessage;
use App\Models\MediaFile;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * 管理画面からの画像送信を検証する。
 *  - 画像ファイルが S3 public に保存され MediaFile が作成される
 *  - LINE push API が type=image で叩かれる
 *  - CustomerLineMessage が message_type=image + media_file_id 付きで保存される
 *  - 機能フラグ OFF / バリデーション失敗時の挙動
 */
class CustomerLineMessageImageSendTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;
    private Customer $customer;
    private CustomerLineContact $contact;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        // Storage::fake() の root に対して、url() が https を返すよう設定（S3 模擬）
        config([
            'filesystems.disks.s3_public' => [
                'driver' => 'local',
                'root' => storage_path('framework/testing/disks/s3_public'),
                'url' => 'https://test-s3.example.com',
                'visibility' => 'public',
            ],
            'line.messaging.channel_access_token' => 'test_token',
            'line.image_messaging.enabled' => true,
            'line.image_messaging.max_size_bytes' => 1024 * 1024,
        ]);
        Storage::fake('s3_public');

        $this->shop = Shop::create([
            'name' => '岡山店',
            'is_active' => true,
            'line_group_id' => 'Cgroup',
        ]);
        $this->customer = Customer::create(['name' => '山田', 'shop_id' => $this->shop->id]);
        $this->contact = CustomerLineContact::create([
            'line_user_id' => 'Utarget',
            'shop_id' => $this->shop->id,
            'customer_id' => $this->customer->id,
            'event_reservation_id' => null,
            'label' => 'お客様',
        ]);
        $this->user = User::factory()->create();
        $this->user->shops()->attach($this->shop->id);
    }

    private function sendUrl(): string
    {
        return route('admin.customers.line.send', [
            'customer' => $this->customer->id,
            'contact' => $this->contact->id,
        ]);
    }

    public function test_admin_send_image_uploads_to_s3_and_calls_line_push(): void
    {
        Http::fake([
            'api.line.me/v2/bot/message/push' => Http::response('', 200),
        ]);
        $file = UploadedFile::fake()->image('test.jpg', 800, 600)->mimeType('image/jpeg');

        $res = $this->actingAs($this->user)->post($this->sendUrl(), [
            'image_file' => $file,
        ]);

        $res->assertOk();
        $body = $res->json('message');
        $this->assertSame('image', $body['message_type']);
        $this->assertNotEmpty($body['image_url']);
        $this->assertNull($body['text']);

        // DB に image タイプで保存
        $msg = CustomerLineMessage::where('customer_line_contact_id', $this->contact->id)->first();
        $this->assertSame('image', $msg->message_type);
        $this->assertSame('outbound', $msg->direction);
        $this->assertNotNull($msg->media_file_id);

        // MediaFile が作られ S3 に存在
        $media = MediaFile::find($msg->media_file_id);
        $this->assertSame('s3', $media->storage_disk);
        $this->assertStringStartsWith('line_outbound/', $media->path);
        Storage::disk('s3_public')->assertExists($media->path);

        // LINE API が type=image で叩かれた
        Http::assertSent(function ($req) {
            if ($req->url() !== 'https://api.line.me/v2/bot/message/push') {
                return false;
            }
            $data = $req->data();
            $msg = $data['messages'][0] ?? null;
            // テスト環境は Storage::fake で http URL になるためプロトコルチェックは緩める
            return $msg
                && $msg['type'] === 'image'
                && !empty($msg['originalContentUrl'])
                && !empty($msg['previewImageUrl']);
        });
    }

    public function test_admin_send_rejects_non_image_file(): void
    {
        Http::fake();
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        // バリデーションエラーは postJson で 422 が返る（通常 POST は 302 リダイレクト）
        $this->actingAs($this->user)
            ->postJson($this->sendUrl(), ['image_file' => $file])
            ->assertStatus(422);

        $this->assertSame(0, CustomerLineMessage::count());
        $this->assertSame(0, MediaFile::count());
        Http::assertNothingSent();
    }

    public function test_admin_send_rejects_oversized_image(): void
    {
        Http::fake();
        // 1500KB の画像（仕様1MB超）
        $file = UploadedFile::fake()->image('big.jpg', 4000, 3000)->size(1500);

        $this->actingAs($this->user)
            ->postJson($this->sendUrl(), ['image_file' => $file])
            ->assertStatus(422);

        $this->assertSame(0, CustomerLineMessage::count());
        Http::assertNothingSent();
    }

    public function test_admin_send_requires_text_or_image(): void
    {
        Http::fake();

        $res = $this->actingAs($this->user)->post($this->sendUrl(), []);

        $res->assertStatus(422);
        $this->assertSame(0, CustomerLineMessage::count());
        Http::assertNothingSent();
    }

    public function test_image_feature_flag_off_ignores_image_and_requires_text(): void
    {
        Http::fake();
        config(['line.image_messaging.enabled' => false]);
        $file = UploadedFile::fake()->image('ok.jpg', 800, 600)->mimeType('image/jpeg');

        // image_file 送っても text が無いので 422
        $this->actingAs($this->user)->post($this->sendUrl(), [
            'image_file' => $file,
        ])->assertStatus(422);

        $this->assertSame(0, CustomerLineMessage::count());
        Http::assertNothingSent();
    }

    public function test_text_only_send_still_works_after_image_feature(): void
    {
        Http::fake([
            'api.line.me/v2/bot/message/push' => Http::response('', 200),
        ]);

        $res = $this->actingAs($this->user)->post($this->sendUrl(), [
            'text' => 'こんにちは',
        ]);

        $res->assertOk();
        $msg = CustomerLineMessage::first();
        $this->assertSame('text', $msg->message_type);
        $this->assertSame('こんにちは', $msg->text);
        $this->assertNull($msg->media_file_id);

        // LINE push に type=text で送られた
        Http::assertSent(function ($req) {
            $data = $req->data();
            return ($data['messages'][0]['type'] ?? null) === 'text';
        });
    }

    public function test_admin_send_returns_502_when_line_push_fails(): void
    {
        Http::fake([
            'api.line.me/v2/bot/message/push' => Http::response(['message' => 'invalid token'], 401),
        ]);
        $file = UploadedFile::fake()->image('test.jpg', 800, 600)->mimeType('image/jpeg');

        $this->actingAs($this->user)->post($this->sendUrl(), [
            'image_file' => $file,
        ])->assertStatus(502);

        // 画像 MediaFile は作られている（S3 アップロード成功）が、メッセージレコードは作られない
        $this->assertSame(1, MediaFile::count());
        $this->assertSame(0, CustomerLineMessage::count());
    }
}
