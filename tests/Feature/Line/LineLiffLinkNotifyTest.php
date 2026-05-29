<?php

namespace Tests\Feature\Line;

use App\Models\Customer;
use App\Models\CustomerLineContact;
use App\Models\CustomerLineLinkToken;
use App\Models\Event;
use App\Models\EventReservation;
use App\Models\Shop;
use App\Services\Line\ShopLineGroupNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * LineLiffController::completeForCustomer / completeForReservation で
 * 連携完了通知が新規時のみ走り、既存連携では再発射しないことを検証する。
 */
class LineLiffLinkNotifyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // LINE 認証および本人挨拶 Push を全モック化（共通）
        Http::fake([
            '*oauth2/v2.1/verify*' => Http::response(['sub' => 'Uliff_user'], 200),
            '*v2/bot/message/push*' => Http::response([], 200),
        ]);
        config([
            'line.liff.login_channel_id' => 'login-ch',
            'line.messaging.channel_access_token' => 'test-token',
            'line.link_welcome_text' => 'welcome',
        ]);
    }

    private function makeCustomerLinkToken(?Shop $shop = null, string $name = '山田 花子'): array
    {
        $shop ??= Shop::create(['name' => '岡山店', 'is_active' => true, 'line_group_id' => 'Cgroup-okayama']);
        $customer = Customer::create(['name' => $name, 'shop_id' => $shop->id]);
        $tokenStr = CustomerLineLinkToken::generateToken();
        CustomerLineLinkToken::query()->create([
            'token' => $tokenStr,
            'customer_id' => $customer->id,
            'shop_id' => $shop->id,
            'suggested_label' => '本人',
            'expires_at' => now()->addDays(7),
            'created_by_user_id' => null,
        ]);

        return [$tokenStr, $shop, $customer];
    }

    public function test_new_customer_link_notifies_shop_group(): void
    {
        [$tokenStr, $shop, $customer] = $this->makeCustomerLinkToken();

        $this->mock(ShopLineGroupNotifier::class, function ($mock) use ($customer) {
            $mock->shouldReceive('notifySystemLinked')
                ->once()
                ->withArgs(function ($contact) use ($customer) {
                    return $contact->customer_id === $customer->id;
                });
        });

        $this->postJson(route('line.liff.complete'), [
            'id_token' => 'dummy',
            'link_token' => $tokenStr,
            'label' => '本人',
        ])->assertOk();
    }

    public function test_relink_to_same_customer_does_not_notify(): void
    {
        [$tokenStr, $shop, $customer] = $this->makeCustomerLinkToken();

        // 既に同じ customer に紐付け済み
        CustomerLineContact::create([
            'line_user_id' => 'Uliff_user',
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'event_reservation_id' => null,
            'label' => 'お客様',
        ]);

        $this->mock(ShopLineGroupNotifier::class, function ($mock) {
            $mock->shouldNotReceive('notifySystemLinked');
        });

        $this->postJson(route('line.liff.complete'), [
            'id_token' => 'dummy',
            'link_token' => $tokenStr,
            'label' => '本人',
        ])->assertOk();
    }

    public function test_new_reservation_link_notifies(): void
    {
        $shop = Shop::create(['name' => '城東店', 'is_active' => true, 'line_group_id' => 'Cgroup-joto']);
        $event = Event::create([
            'slug' => 'rsv-event',
            'title' => 'リンクテスト',
            'description' => '',
            'form_type' => 'reservation',
            'is_public' => true,
        ]);
        $event->shops()->attach($shop->id);

        $reservation = EventReservation::create([
            'event_id' => $event->id,
            'name' => '田中 太郎',
            'email' => 't@t',
            'phone' => '090-0000-0001',
            'status' => 'pending',
            'privacy_agreed' => true,
            'cancel_flg' => false,
            'visitor_count' => 1,
        ]);

        $tokenStr = CustomerLineLinkToken::generateToken();
        CustomerLineLinkToken::query()->create([
            'token' => $tokenStr,
            'event_reservation_id' => $reservation->id,
            'shop_id' => $shop->id,
            'suggested_label' => '本人',
            'expires_at' => now()->addDays(7),
            'created_by_user_id' => null,
        ]);

        $this->mock(ShopLineGroupNotifier::class, function ($mock) use ($reservation) {
            $mock->shouldReceive('notifySystemLinked')
                ->once()
                ->withArgs(function ($contact) use ($reservation) {
                    return $contact->event_reservation_id === $reservation->id;
                });
        });

        $this->postJson(route('line.liff.complete'), [
            'id_token' => 'dummy',
            'link_token' => $tokenStr,
        ])->assertOk();
    }

    // 注: 別の予約への乗り換えは LineLiffController 側で 422 拒否される仕様のため、
    // 「reservation を別物に変えて再連携→通知」というシナリオはテスト対象外。

    public function test_link_with_empty_shop_group_id_completes_without_exception(): void
    {
        // shop.line_group_id 空でも例外は出ない（resolveGroupId が null を返してスキップ）
        $shop = Shop::create(['name' => '無グループ店', 'is_active' => true, 'line_group_id' => null]);
        [$tokenStr] = $this->makeCustomerLinkToken($shop);

        // 通知 method 自体は呼ばれるが内部スキップされる → 呼ばれることだけ確認
        $this->mock(ShopLineGroupNotifier::class, function ($mock) {
            $mock->shouldReceive('notifySystemLinked')->once();
        });

        $this->postJson(route('line.liff.complete'), [
            'id_token' => 'dummy',
            'link_token' => $tokenStr,
            'label' => '本人',
        ])->assertOk();
    }
}
