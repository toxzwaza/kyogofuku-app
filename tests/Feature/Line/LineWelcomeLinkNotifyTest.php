<?php

namespace Tests\Feature\Line;

use App\Models\Customer;
use App\Models\CustomerLineContact;
use App\Models\Shop;
use App\Services\Line\ShopLineGroupNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * LineWelcomeLinkController::linkToCustomer / linkToReservation で
 * 自己紐付け（あいさつメッセージ経由）完了時の通知を検証する。
 */
class LineWelcomeLinkNotifyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake([
            '*oauth2/v2.1/verify*' => Http::response(['sub' => 'Uwelcome_user'], 200),
            '*v2/bot/message/push*' => Http::response([], 200),
        ]);
        config([
            'line.liff.login_channel_id' => 'login-ch',
            'line.messaging.channel_access_token' => 'test-token',
            'line.link_welcome_text' => 'welcome',
        ]);
    }

    public function test_new_self_link_to_customer_notifies(): void
    {
        $shop = Shop::create(['name' => '岡山店', 'is_active' => true, 'line_group_id' => 'Cgroup-okayama']);
        $customer = Customer::create([
            'name' => '山田 花子',
            'kana' => 'ヤマダ ハナコ',
            'phone_number' => '090-1111-2222',
            'shop_id' => $shop->id,
        ]);

        $this->mock(ShopLineGroupNotifier::class, function ($mock) use ($customer) {
            $mock->shouldReceive('notifySystemLinked')
                ->once()
                ->withArgs(function ($contact) use ($customer) {
                    return $contact->customer_id === $customer->id;
                });
        });

        $this->postJson(route('line.liff.welcome.match'), [
            'id_token' => 'dummy',
            'lookup_key' => '09011112222',
            'kana' => 'ヤマダハナコ',
        ])->assertOk();

        $this->assertDatabaseHas('customer_line_contacts', [
            'line_user_id' => 'Uwelcome_user',
            'customer_id' => $customer->id,
        ]);
    }

    public function test_already_linked_customer_does_not_notify(): void
    {
        $shop = Shop::create(['name' => '岡山店', 'is_active' => true, 'line_group_id' => 'Cgroup-okayama']);
        $customer = Customer::create([
            'name' => '山田 花子',
            'phone_number' => '090-1111-2222',
            'shop_id' => $shop->id,
        ]);

        // 既に紐付け済み
        CustomerLineContact::create([
            'line_user_id' => 'Uwelcome_user',
            'shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'event_reservation_id' => null,
            'label' => 'お客様',
        ]);

        $this->mock(ShopLineGroupNotifier::class, function ($mock) {
            $mock->shouldNotReceive('notifySystemLinked');
        });

        // already_linked のレスポンスが返る
        $this->postJson(route('line.liff.welcome.match'), [
            'id_token' => 'dummy',
            'lookup_key' => '09011112222',
            'kana' => '',
        ])->assertOk()
          ->assertJsonFragment(['already_linked' => true]);
    }

    public function test_no_match_does_not_notify(): void
    {
        $this->mock(ShopLineGroupNotifier::class, function ($mock) {
            $mock->shouldNotReceive('notifySystemLinked');
        });

        // 該当する顧客・予約なし → 404
        $this->postJson(route('line.liff.welcome.match'), [
            'id_token' => 'dummy',
            'lookup_key' => '09099999999',
        ])->assertStatus(404);
    }
}
