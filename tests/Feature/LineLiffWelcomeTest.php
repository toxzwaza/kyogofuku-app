<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerLineContact;
use App\Models\CustomerLineLinkToken;
use App\Models\CustomerLineMessage;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LineLiffWelcomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_sends_welcome_push_and_stores_outbound_on_first_link(): void
    {
        Http::fake([
            '*oauth2/v2.1/verify*' => Http::response(['sub' => 'Uwelcome_new'], 200),
            '*v2/bot/message/push*' => Http::response([], 200),
        ]);

        config(['line.liff.login_channel_id' => 'login-ch']);
        config(['line.messaging.channel_access_token' => 'test-token']);
        config(['line.link_welcome_text' => "LINE連携が完了しました。\n\nテスト挨拶です。"]);

        $shop = Shop::create([
            'name' => 'S',
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'name' => 'C',
            'shop_id' => $shop->id,
        ]);
        $tokenStr = CustomerLineLinkToken::generateToken();
        CustomerLineLinkToken::query()->create([
            'token' => $tokenStr,
            'customer_id' => $customer->id,
            'shop_id' => $shop->id,
            'suggested_label' => '本人',
            'expires_at' => now()->addDays(7),
            'created_by_user_id' => null,
        ]);

        $this->postJson(route('line.liff.complete'), [
            'id_token' => 'dummy-token',
            'link_token' => $tokenStr,
            'label' => '本人',
        ])->assertOk();

        $contact = CustomerLineContact::query()->where('line_user_id', 'Uwelcome_new')->first();
        $this->assertNotNull($contact);
        $this->assertDatabaseHas('customer_line_messages', [
            'customer_line_contact_id' => $contact->id,
            'direction' => CustomerLineMessage::DIRECTION_OUTBOUND,
        ]);
    }

    public function test_complete_skips_welcome_when_contact_already_existed(): void
    {
        Http::fake([
            '*oauth2/v2.1/verify*' => Http::response(['sub' => 'Uwelcome_old'], 200),
            '*v2/bot/message/push*' => Http::response([], 200),
        ]);

        config(['line.liff.login_channel_id' => 'login-ch']);
        config(['line.messaging.channel_access_token' => 'test-token']);
        config(['line.link_welcome_text' => '再連携時は送らない']);

        $shop = Shop::create([
            'name' => 'S',
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'name' => 'C',
            'shop_id' => $shop->id,
        ]);
        CustomerLineContact::query()->create([
            'customer_id' => $customer->id,
            'shop_id' => $shop->id,
            'line_user_id' => 'Uwelcome_old',
            'label' => '旧',
        ]);

        $tokenStr = CustomerLineLinkToken::generateToken();
        CustomerLineLinkToken::query()->create([
            'token' => $tokenStr,
            'customer_id' => $customer->id,
            'shop_id' => $shop->id,
            'suggested_label' => '本人',
            'expires_at' => now()->addDays(7),
            'created_by_user_id' => null,
        ]);

        $beforeCount = CustomerLineMessage::query()->count();

        $this->postJson(route('line.liff.complete'), [
            'id_token' => 'dummy-token',
            'link_token' => $tokenStr,
            'label' => '更新ラベル',
        ])->assertOk();

        $pushCalls = collect(Http::recorded())->filter(function (array $pair) {
            return str_contains($pair[0]->url(), 'v2/bot/message/push');
        });
        $this->assertCount(0, $pushCalls);

        $this->assertSame($beforeCount, CustomerLineMessage::query()->count());
    }
}
