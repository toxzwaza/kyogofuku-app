<?php

namespace Tests\Feature\Line;

use App\Models\Customer;
use App\Models\CustomerLineContact;
use App\Models\CustomerLineMessage;
use App\Models\LineUnknownInboundMessage;
use App\Models\MediaFile;
use App\Models\Shop;
use App\Services\Line\ShopLineGroupNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * LINE Webhook で画像メッセージを受信したとき、
 * LINE API から content を DL → S3 に保存 → CustomerLineMessage に media_file_id 付きで記録できるか検証。
 */
class InboundImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'line.messaging.channel_secret' => 'test_secret',
            'line.messaging.channel_access_token' => 'test_token',
            'line.image_messaging.enabled' => true,
        ]);
        Storage::fake('s3_public');
    }

    private function postWebhook(array $events): \Illuminate\Testing\TestResponse
    {
        $body = json_encode(['destination' => 'Ufake', 'events' => $events]);
        $signature = base64_encode(hash_hmac('sha256', $body, 'test_secret', true));

        return $this->call(
            'POST',
            '/webhook/line/messaging',
            [], [], [],
            ['HTTP_X-LINE-SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $body,
        );
    }

    private function makeLinkedContact(): CustomerLineContact
    {
        $shop = Shop::create([
            'name' => '岡山店',
            'is_active' => true,
            'line_group_id' => 'Cgroup',
        ]);
        $customer = Customer::create(['name' => '山田', 'shop_id' => $shop->id]);

        return CustomerLineContact::create([
            'line_user_id' => 'Ulinked',
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'event_reservation_id' => null,
            'label' => 'お客様',
        ]);
    }

    private function mockLineImageDownload(string $messageId, string $mime = 'image/jpeg', string $bytes = "\xff\xd8\xff\xe0fakejpegbinary"): void
    {
        Http::fake([
            'api-data.line.me/v2/bot/message/'.$messageId.'/content' => Http::response($bytes, 200, ['Content-Type' => $mime]),
        ]);
    }

    public function test_inbound_image_from_linked_user_stores_to_s3_and_creates_message(): void
    {
        $contact = $this->makeLinkedContact();
        $this->mockLineImageDownload('img-001');

        // 店舗通知も走るが、テキスト固定なので呼び出しが起きることだけ確認
        $this->mock(ShopLineGroupNotifier::class, function ($mock) {
            $mock->shouldReceive('notifyInboundMessage')->once();
        });

        $this->postWebhook([[
            'type' => 'message',
            'source' => ['type' => 'user', 'userId' => 'Ulinked'],
            'message' => ['type' => 'image', 'id' => 'img-001', 'contentProvider' => ['type' => 'line']],
        ]])->assertOk();

        // CustomerLineMessage が image タイプで保存される
        $msg = CustomerLineMessage::where('line_message_id', 'img-001')->first();
        $this->assertNotNull($msg);
        $this->assertSame('image', $msg->message_type);
        $this->assertNull($msg->text);
        $this->assertNotNull($msg->media_file_id);
        $this->assertSame($contact->id, $msg->customer_line_contact_id);

        // MediaFile が作成され、S3 に画像が保存されている
        $media = MediaFile::find($msg->media_file_id);
        $this->assertNotNull($media);
        $this->assertSame('s3', $media->storage_disk);
        $this->assertSame('image/jpeg', $media->mime_type);
        $this->assertStringStartsWith('line_inbound/', $media->path);
        Storage::disk('s3_public')->assertExists($media->path);
    }

    public function test_inbound_image_from_unlinked_user_records_to_unknown(): void
    {
        $this->mockLineImageDownload('img-002');

        $this->postWebhook([[
            'type' => 'message',
            'source' => ['type' => 'user', 'userId' => 'Uunknown'],
            'message' => ['type' => 'image', 'id' => 'img-002'],
        ]])->assertOk();

        // CustomerLineMessage は作成されない
        $this->assertDatabaseMissing('customer_line_messages', ['line_message_id' => 'img-002']);
        // LineUnknownInboundMessage に画像参照付きで残る
        $unknown = LineUnknownInboundMessage::where('line_message_id', 'img-002')->first();
        $this->assertNotNull($unknown);
        $this->assertStringContainsString('[画像]', (string) $unknown->text);
        // MediaFile も作られている
        $this->assertSame(1, MediaFile::count());
    }

    public function test_inbound_image_failure_records_to_unknown_with_failure_note(): void
    {
        $this->makeLinkedContact();
        // LINE API が 410 Gone を返す (10分経過想定)
        Http::fake([
            'api-data.line.me/v2/bot/message/img-gone/content' => Http::response('', 410),
        ]);

        $this->postWebhook([[
            'type' => 'message',
            'source' => ['type' => 'user', 'userId' => 'Ulinked'],
            'message' => ['type' => 'image', 'id' => 'img-gone'],
        ]])->assertOk();

        // CustomerLineMessage は作成されない（DL 失敗のため）
        $this->assertDatabaseMissing('customer_line_messages', ['line_message_id' => 'img-gone']);
        // LineUnknownInboundMessage に「画像受信失敗」が残る
        $this->assertDatabaseHas('line_unknown_inbound_messages', [
            'line_message_id' => 'img-gone',
            'text' => '[画像受信失敗]',
        ]);
        // S3 にもファイルは無い
        $this->assertSame(0, MediaFile::count());
    }

    public function test_image_feature_flag_off_falls_back_to_unknown(): void
    {
        $this->makeLinkedContact();
        config(['line.image_messaging.enabled' => false]);
        // HTTP fake 不要：機能 OFF なら LINE API は叩かれない
        Http::fake();

        $this->postWebhook([[
            'type' => 'message',
            'source' => ['type' => 'user', 'userId' => 'Ulinked'],
            'message' => ['type' => 'image', 'id' => 'img-flagoff'],
        ]])->assertOk();

        // 機能 OFF のため image は処理されず、非テキスト扱いで unknown へ
        $this->assertDatabaseMissing('customer_line_messages', ['line_message_id' => 'img-flagoff']);
        $this->assertDatabaseHas('line_unknown_inbound_messages', ['line_message_id' => 'img-flagoff']);
        // LINE API も叩かれていない
        Http::assertNothingSent();
    }

    public function test_duplicate_image_message_id_is_ignored(): void
    {
        $contact = $this->makeLinkedContact();
        $existingMedia = MediaFile::create([
            'original_filename' => 'old.jpg',
            'path' => 'line_inbound/old.jpg',
            'storage_disk' => 's3',
            'mime_type' => 'image/jpeg',
        ]);
        CustomerLineMessage::create([
            'customer_line_contact_id' => $contact->id,
            'direction' => CustomerLineMessage::DIRECTION_INBOUND,
            'message_type' => 'image',
            'line_message_id' => 'img-dup',
            'media_file_id' => $existingMedia->id,
        ]);

        // HTTP fake で LINE API が叩かれないことも確認したい
        Http::fake();

        $this->postWebhook([[
            'type' => 'message',
            'source' => ['type' => 'user', 'userId' => 'Ulinked'],
            'message' => ['type' => 'image', 'id' => 'img-dup'],
        ]])->assertOk();

        $this->assertSame(1, CustomerLineMessage::where('line_message_id', 'img-dup')->count());
        Http::assertNothingSent();
    }
}
