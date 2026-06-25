<?php

namespace Tests\Feature\Referral;

use App\Models\Customer;
use App\Models\CustomerLineContact;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\Shop;
use App\Services\Referral\ReferralPointService;
use Database\Seeders\ReferralSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * LIFFエンドポイント。IDトークン検証(LINE verify API)は Http::fake でモックする。
 */
class ReferralLiffTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferralSettingsSeeder::class);
        $this->shop = Shop::create(['name' => '店', 'is_active' => true]);
        config(['line.liff.login_channel_id' => '2009633621']);
    }

    /** verify と push をモック。verify は sub を返す。 */
    private function fakeLine(string $sub): void
    {
        Http::fake([
            '*oauth2/v2.1/verify*' => Http::response(['sub' => $sub], 200),
            '*api.line.me/v2/bot/message/push*' => Http::response([], 200),
        ]);
    }

    private function customer(string $name = '顧客'): Customer
    {
        return Customer::create(['name' => $name, 'shop_id' => $this->shop->id]);
    }

    private function link(Customer $c, string $lineUserId): void
    {
        CustomerLineContact::create([
            'customer_id' => $c->id,
            'shop_id' => $this->shop->id,
            'line_user_id' => $lineUserId,
            'label' => '本人',
        ]);
    }

    public function test_referral_link_creates_referral_and_pushes(): void
    {
        $this->fakeLine('Ufriend');
        $referrer = $this->customer('紹介者');
        $code = ReferralCode::create(['customer_id' => $referrer->id, 'code' => 'ABC12345']);

        $this->postJson(route('line.liff.referral.link'), ['id_token' => 'tok', 'ref' => $code->code])
            ->assertOk()
            ->assertJson(['state' => 'linked']);

        $this->assertDatabaseHas('referrals', [
            'referrer_customer_id' => $referrer->id,
            'referred_line_user_id' => 'Ufriend',
            'status' => 'linked',
        ]);
        // お礼テキストPushが送られた
        Http::assertSent(fn ($req) => str_contains($req->url(), '/v2/bot/message/push'));
    }

    public function test_referral_link_unauthorized_without_valid_token(): void
    {
        Http::fake(['*oauth2/v2.1/verify*' => Http::response(['error' => 'invalid'], 400)]);
        $referrer = $this->customer('紹介者');
        $code = ReferralCode::create(['customer_id' => $referrer->id, 'code' => 'ABC12345']);

        $this->postJson(route('line.liff.referral.link'), ['id_token' => 'bad', 'ref' => $code->code])
            ->assertStatus(401);
    }

    public function test_my_points_data_for_linked_customer(): void
    {
        $this->fakeLine('Ume');
        $customer = $this->customer('本人');
        $this->link($customer, 'Ume');
        app(ReferralPointService::class)->applyDelta($customer, 5000, 'adjust');

        $this->postJson(route('line.liff.my-points.data'), ['id_token' => 'tok'])
            ->assertOk()
            ->assertJson(['state' => 'ok', 'balance' => 5000]);
    }

    public function test_my_points_data_not_linked(): void
    {
        $this->fakeLine('Unobody');

        $this->postJson(route('line.liff.my-points.data'), ['id_token' => 'tok'])
            ->assertStatus(403)
            ->assertJson(['state' => 'not_linked']);
    }

    public function test_mypage_data_returns_customer_info(): void
    {
        $this->fakeLine('Ump');
        $customer = $this->customer('山田花子');
        $this->link($customer, 'Ump');

        $this->postJson(route('line.liff.mypage.data'), ['id_token' => 'tok'])
            ->assertOk()
            ->assertJson(['state' => 'ok', 'customer' => ['name' => '山田花子']]);
    }
}
