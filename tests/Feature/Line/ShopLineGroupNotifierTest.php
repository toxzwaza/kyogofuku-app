<?php

namespace Tests\Feature\Line;

use App\Http\Controllers\LineWebhookController;
use App\Models\Customer;
use App\Models\CustomerLineContact;
use App\Models\EventReservation;
use App\Models\Shop;
use App\Services\Line\ShopLineGroupNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * ShopLineGroupNotifier の単体ロジック検証。
 *
 * pushToLineGroup は Guzzle で直接 HTTP 送信しているため、
 * LineWebhookController 全体を Mockery で差し替えて、
 * 「どの引数で何回呼ばれたか」のみを検証する。
 */
class ShopLineGroupNotifierTest extends TestCase
{
    use RefreshDatabase;

    private function makeContact(array $contactAttrs = [], array $shopAttrs = []): CustomerLineContact
    {
        $shop = Shop::create(array_merge([
            'name' => 'テスト店舗',
            'is_active' => true,
            'line_group_id' => 'Cgroup-test',
        ], $shopAttrs));

        $customer = null;
        $customerId = null;
        if (! ($contactAttrs['skip_customer'] ?? false)) {
            $customer = Customer::create([
                'name' => $contactAttrs['customer_name'] ?? '山田 花子',
                'shop_id' => $shop->id,
            ]);
            $customerId = $customer->id;
        }
        unset($contactAttrs['skip_customer'], $contactAttrs['customer_name']);

        return CustomerLineContact::create(array_merge([
            'line_user_id' => 'U' . str_repeat('a', 32),
            'shop_id' => $shop->id,
            'customer_id' => $customerId,
            'event_reservation_id' => null,
            'label' => 'お客様',
        ], $contactAttrs));
    }

    public function test_notify_system_linked_with_customer(): void
    {
        $contact = $this->makeContact();

        $this->mock(LineWebhookController::class, function ($mock) use ($contact) {
            $mock->shouldReceive('pushToLineGroup')
                ->once()
                ->withArgs(function ($message, $groupId, $url, $label) {
                    return $message === '山田 花子さんがシステム連携が完了しました。'
                        && $groupId === 'Cgroup-test'
                        && str_contains($url, '/admin/customers/')
                        && $label === '詳細を開く';
                });
        });

        app(ShopLineGroupNotifier::class)->notifySystemLinked($contact);
    }

    public function test_notify_system_linked_with_reservation(): void
    {
        $shop = Shop::create([
            'name' => 'テスト店舗',
            'is_active' => true,
            'line_group_id' => 'Cgroup-rsv',
        ]);

        // 関連 event を作成
        $event = \App\Models\Event::create([
            'slug' => 'test-event',
            'title' => 'テストイベント',
            'description' => '',
            'form_type' => 'reservation',
            'is_public' => true,
        ]);

        $reservation = EventReservation::create([
            'event_id' => $event->id,
            'name' => '田中 太郎',
            'email' => 't@test',
            'phone' => '090-0000-0000',
            'status' => 'pending',
            'privacy_agreed' => true,
            'cancel_flg' => false,
            'visitor_count' => 1,
        ]);

        $contact = CustomerLineContact::create([
            'line_user_id' => 'U' . str_repeat('b', 32),
            'shop_id' => $shop->id,
            'customer_id' => null,
            'event_reservation_id' => $reservation->id,
            'label' => '本人',
        ]);

        $this->mock(LineWebhookController::class, function ($mock) {
            $mock->shouldReceive('pushToLineGroup')
                ->once()
                ->withArgs(function ($message, $groupId, $url, $label) {
                    return $message === '田中 太郎さんがシステム連携が完了しました。'
                        && $groupId === 'Cgroup-rsv'
                        && str_contains($url, '/admin/reservations/')
                        && $label === '詳細を開く';
                });
        });

        app(ShopLineGroupNotifier::class)->notifySystemLinked($contact);
    }

    public function test_notify_system_linked_falls_back_to_label(): void
    {
        $contact = $this->makeContact(['skip_customer' => true, 'label' => 'マイLABEL']);

        $this->mock(LineWebhookController::class, function ($mock) {
            $mock->shouldReceive('pushToLineGroup')
                ->once()
                ->withArgs(function ($message) {
                    // customer/reservation 無し、label のみ
                    return str_starts_with($message, 'マイLABELさんがシステム連携が完了しました。');
                });
        });

        app(ShopLineGroupNotifier::class)->notifySystemLinked($contact);
    }

    public function test_notify_system_linked_skipped_when_group_id_empty(): void
    {
        $contact = $this->makeContact([], ['line_group_id' => null]);

        // 1回も呼ばれないことを検証
        $this->mock(LineWebhookController::class, function ($mock) {
            $mock->shouldNotReceive('pushToLineGroup');
        });

        app(ShopLineGroupNotifier::class)->notifySystemLinked($contact);
    }

    public function test_notify_inbound_message_concatenates_text_with_newline(): void
    {
        $contact = $this->makeContact();

        $this->mock(LineWebhookController::class, function ($mock) {
            $mock->shouldReceive('pushToLineGroup')
                ->once()
                ->withArgs(function ($message) {
                    return $message === "山田 花子さんからメッセージを受信しました。\nこんにちは、ご相談です。";
                });
        });

        app(ShopLineGroupNotifier::class)->notifyInboundMessage($contact, 'こんにちは、ご相談です。');
    }

    public function test_notify_inbound_message_falls_back_to_default_name(): void
    {
        // label を空文字にすると filled() で false 判定され 'お客様' フォールバック
        $contact = $this->makeContact(['skip_customer' => true, 'label' => '']);

        $this->mock(LineWebhookController::class, function ($mock) {
            $mock->shouldReceive('pushToLineGroup')
                ->once()
                ->withArgs(function ($message) {
                    return str_starts_with($message, "お客様さんからメッセージを受信しました。\nテスト");
                });
        });

        app(ShopLineGroupNotifier::class)->notifyInboundMessage($contact, 'テスト');
    }

    public function test_push_failure_is_logged_and_swallowed(): void
    {
        $contact = $this->makeContact();

        // pushToLineGroup 内で例外発生する状況
        $this->mock(LineWebhookController::class, function ($mock) {
            $mock->shouldReceive('pushToLineGroup')
                ->once()
                ->andThrow(new \RuntimeException('LINE API down'));
        });

        // Log::warning が呼ばれることを検証
        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function ($message, $context) {
                return str_contains($message, 'LINE通知失敗')
                    && ($context['kind'] ?? null) === 'system_linked'
                    && ($context['error'] ?? null) === 'LINE API down';
            });

        // 例外が呼び出し元に伝播しないこと
        app(ShopLineGroupNotifier::class)->notifySystemLinked($contact);

        $this->assertTrue(true); // 例外なしで到達できればOK
    }
}
