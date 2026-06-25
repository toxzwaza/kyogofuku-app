<?php

namespace Tests\Feature\Referral;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerLineContact;
use App\Models\Plan;
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
        config(['line.referral.liff_url' => 'https://liff.line.me/2009633621-Wh0yxjfu']);
    }

    /** 成約済（確定contract）顧客を作る。ContractObserver が紹介コードを発行する。 */
    private function contractedCustomer(string $name = '紹介者'): Customer
    {
        $plan = Plan::firstOrCreate(['code' => 'PTEST'], ['name' => '振袖', 'is_active' => true]);
        $c = $this->customer($name);
        Contract::create([
            'customer_id' => $c->id,
            'shop_id' => $this->shop->id,
            'plan_id' => $plan->id,
            'contract_date' => today(),
            'kimono_type' => '振袖',
            'total_amount' => 200000,
            'status' => '確定',
        ]);

        return $c;
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

        $res = $this->postJson(route('line.liff.my-points.data'), ['id_token' => 'tok'])
            ->assertOk()
            ->assertJson(['state' => 'ok', 'balance' => 5000])
            // 還元率（ブロンズ=3%）と次ステージ（シルバー：あと4件）
            ->assertJsonPath('reward_rate', 3)
            ->assertJsonPath('next_stage.stage', 'silver')
            ->assertJsonPath('next_stage.remaining', 4);
        // ステージ章は data URI（ngrok等でも確実に表示するため外部URLにしない）
        $this->assertStringStartsWith('data:image/png;base64,', (string) $res->json('stage_badge'));
        $res->assertJsonStructure(['coupons']);
    }

    public function test_my_points_data_not_linked(): void
    {
        $this->fakeLine('Unobody');

        $this->postJson(route('line.liff.my-points.data'), ['id_token' => 'tok'])
            ->assertStatus(403)
            ->assertJson(['state' => 'not_linked']);
    }

    public function test_me_returns_code_for_contracted_customer(): void
    {
        $this->fakeLine('Uref1');
        $customer = $this->contractedCustomer();
        $this->link($customer, 'Uref1');

        $res = $this->postJson(route('line.liff.referral.me'), ['id_token' => 'tok'])
            ->assertOk()
            ->assertJson(['state' => 'ok']);

        $code = $res->json('code');
        $this->assertNotEmpty($code);
        $this->assertStringContainsString('?ref='.$code, $res->json('share_url'));
        // ContractObserver が成約時にコードを発行している
        $this->assertDatabaseHas('referral_codes', ['customer_id' => $customer->id, 'code' => $code]);
    }

    public function test_me_not_eligible_without_contract(): void
    {
        $this->fakeLine('Uref2');
        $customer = $this->customer('未成約');
        $this->link($customer, 'Uref2');

        $this->postJson(route('line.liff.referral.me'), ['id_token' => 'tok'])
            ->assertOk()
            ->assertJson(['state' => 'not_eligible']);
        $this->assertDatabaseMissing('referral_codes', ['customer_id' => $customer->id]);
    }

    public function test_me_not_linked(): void
    {
        $this->fakeLine('Unobody2');

        $this->postJson(route('line.liff.referral.me'), ['id_token' => 'tok'])
            ->assertStatus(403)
            ->assertJson(['state' => 'not_linked']);
    }

    public function test_unlink_removes_contact(): void
    {
        $this->fakeLine('Uunlink');
        $customer = $this->customer('解除する人');
        $this->link($customer, 'Uunlink');
        $this->assertDatabaseHas('customer_line_contacts', ['line_user_id' => 'Uunlink']);

        $this->postJson(route('line.liff.unlink'), ['id_token' => 'tok'])
            ->assertOk()
            ->assertJson(['state' => 'unlinked']);

        $this->assertDatabaseMissing('customer_line_contacts', ['line_user_id' => 'Uunlink']);
    }

    public function test_unlink_unauthorized_without_valid_token(): void
    {
        Http::fake(['*oauth2/v2.1/verify*' => Http::response(['error' => 'invalid'], 400)]);

        $this->postJson(route('line.liff.unlink'), ['id_token' => 'bad'])
            ->assertStatus(401);
    }

    public function test_mypage_data_returns_customer_info(): void
    {
        $this->fakeLine('Ump');
        // 成約あり顧客（contract_date が文字列でも format で落ちないことを保証）
        $customer = $this->contractedCustomer('山田花子');
        $this->link($customer, 'Ump');

        $this->postJson(route('line.liff.mypage.data'), ['id_token' => 'tok'])
            ->assertOk()
            ->assertJson(['state' => 'ok', 'customer' => ['name' => '山田花子']])
            ->assertJsonPath('contracts.0.contract_date', today()->format('Y-m-d'))
            ->assertJsonPath('contracts.0.total_amount', 200000);
    }
}
