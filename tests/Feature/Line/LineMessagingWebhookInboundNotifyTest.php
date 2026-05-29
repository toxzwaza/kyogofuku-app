<?php

namespace Tests\Feature\Line;

use App\Models\Customer;
use App\Models\CustomerLineContact;
use App\Models\CustomerLineMessage;
use App\Models\LineUnknownInboundMessage;
use App\Models\Shop;
use App\Services\Line\ShopLineGroupNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * LINE Messaging Webhook 受信時に、紐付け済みユーザーからのテキストで
 * 店舗グループ通知が走るか／走らないかを検証する。
 */
class LineMessagingWebhookInboundNotifyTest extends TestCase
{
    use RefreshDatabase;

    private function postWebhook(array $events): \Illuminate\Testing\TestResponse
    {
        $secret = 'test_secret';
        config(['line.messaging.channel_secret' => $secret]);

        $body = json_encode(['destination' => 'Ufake', 'events' => $events]);
        // HMAC-SHA256 で署名
        $signature = base64_encode(hash_hmac('sha256', $body, $secret, true));

        return $this->call(
            'POST',
            '/webhook/line/messaging',
            [], [], [],
            ['HTTP_X-LINE-SIGNATURE' => $signature, 'CONTENT_TYPE' => 'application/json'],
            $body,
        );
    }

    private function makeContact(): CustomerLineContact
    {
        $shop = Shop::create([
            'name' => '岡山店',
            'is_active' => true,
            'line_group_id' => 'Cgroup-okayama',
        ]);
        $customer = Customer::create([
            'name' => '山田 花子',
            'shop_id' => $shop->id,
        ]);

        return CustomerLineContact::create([
            'line_user_id' => 'Ulinked_user',
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'event_reservation_id' => null,
            'label' => 'お客様',
        ]);
    }

    public function test_inbound_text_from_linked_user_notifies_shop_group(): void
    {
        $contact = $this->makeContact();

        // ShopLineGroupNotifier をモック化（実 LINE API には送信させない）
        $this->mock(ShopLineGroupNotifier::class, function ($mock) use ($contact) {
            $mock->shouldReceive('notifyInboundMessage')
                ->once()
                ->withArgs(function ($received, $text) use ($contact) {
                    return $received->id === $contact->id && $text === 'こんにちは';
                });
        });

        $this->postWebhook([[
            'type' => 'message',
            'source' => ['type' => 'user', 'userId' => 'Ulinked_user'],
            'message' => ['type' => 'text', 'id' => 'msg-001', 'text' => 'こんにちは'],
        ]])->assertOk();

        $this->assertDatabaseHas('customer_line_messages', [
            'customer_line_contact_id' => $contact->id,
            'line_message_id' => 'msg-001',
            'text' => 'こんにちは',
        ]);
    }

    public function test_inbound_text_from_unlinked_user_does_not_notify(): void
    {
        // ShopLineGroupNotifier のモックは「呼ばれない」期待
        $this->mock(ShopLineGroupNotifier::class, function ($mock) {
            $mock->shouldNotReceive('notifyInboundMessage');
        });

        $this->postWebhook([[
            'type' => 'message',
            'source' => ['type' => 'user', 'userId' => 'Uunknown_user'],
            'message' => ['type' => 'text', 'id' => 'msg-002', 'text' => '未紐付けユーザー'],
        ]])->assertOk();

        // 未紐付けは LineUnknownInboundMessage に保存される
        $this->assertDatabaseHas('line_unknown_inbound_messages', [
            'line_user_id' => 'Uunknown_user',
            'line_message_id' => 'msg-002',
        ]);
    }

    public function test_duplicate_line_message_id_does_not_notify_twice(): void
    {
        $contact = $this->makeContact();

        // 既存メッセージ
        CustomerLineMessage::create([
            'customer_line_contact_id' => $contact->id,
            'direction' => CustomerLineMessage::DIRECTION_INBOUND,
            'message_type' => 'text',
            'text' => '既存',
            'line_message_id' => 'msg-dup',
            'payload' => null,
        ]);

        // 通知メソッドは呼ばれないはず
        $this->mock(ShopLineGroupNotifier::class, function ($mock) {
            $mock->shouldNotReceive('notifyInboundMessage');
        });

        $this->postWebhook([[
            'type' => 'message',
            'source' => ['type' => 'user', 'userId' => 'Ulinked_user'],
            'message' => ['type' => 'text', 'id' => 'msg-dup', 'text' => 'リプレイ'],
        ]])->assertOk();

        // 重複 INSERT が起きていない（合計 1件のまま）
        $this->assertEquals(1, CustomerLineMessage::where('line_message_id', 'msg-dup')->count());
    }

    public function test_follow_event_does_not_notify(): void
    {
        // 友達追加（follow）は スコープ外
        $this->mock(ShopLineGroupNotifier::class, function ($mock) {
            $mock->shouldNotReceive('notifyInboundMessage');
            $mock->shouldNotReceive('notifySystemLinked');
        });

        $this->postWebhook([[
            'type' => 'follow',
            'source' => ['type' => 'user', 'userId' => 'Unew_follower'],
        ]])->assertOk();
    }

    public function test_non_text_message_is_not_notified(): void
    {
        $this->makeContact();

        // スタンプメッセージ → LineUnknownInboundMessage 行き
        $this->mock(ShopLineGroupNotifier::class, function ($mock) {
            $mock->shouldNotReceive('notifyInboundMessage');
        });

        $this->postWebhook([[
            'type' => 'message',
            'source' => ['type' => 'user', 'userId' => 'Ulinked_user'],
            'message' => ['type' => 'sticker', 'id' => 'msg-sticker', 'packageId' => '1', 'stickerId' => '2'],
        ]])->assertOk();
    }
}
