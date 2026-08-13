<?php

namespace Tests\Feature\Referral;

use App\Models\Customer;
use App\Models\Plan;
use App\Models\PointLedger;
use App\Models\Referral;
use App\Models\Shop;
use App\Models\User;
use App\Services\Referral\PointGrantService;
use Database\Seeders\ReferralSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * feature/line-referral-enhancement:
 * ①成約金額の必須化 ②ポイント付与一覧の顧客絞り込み ③お友達紹介ブロック（紹介者変更）
 */
class ReferralEnhancementTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        $this->seed(ReferralSettingsSeeder::class);
        $this->shop = Shop::create(['name' => 'テスト店', 'is_active' => true]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->shops()->attach($this->shop->id);

        return $user;
    }

    private function customer(string $name = '顧客', array $attrs = []): Customer
    {
        return Customer::create(array_merge(['name' => $name, 'shop_id' => $this->shop->id], $attrs));
    }

    // ===== ① 成約金額の必須化 =====

    public function test_contract_store_requires_total_amount(): void
    {
        $plan = Plan::firstOrCreate(['code' => 'PT1'], ['name' => '振袖', 'is_active' => true]);
        $customer = $this->customer('成約テスト');

        $payload = [
            'shop_id' => $this->shop->id,
            'plan_id' => $plan->id,
            'contract_date' => '2026-08-13',
            'kimono_type' => '振袖',
            'status' => '確定',
        ];

        $this->actingAs($this->admin())
            ->post(route('admin.customers.contracts.store', $customer), $payload)
            ->assertSessionHasErrors('total_amount');

        $this->assertDatabaseMissing('contracts', ['customer_id' => $customer->id]);

        $this->actingAs($this->admin())
            ->post(route('admin.customers.contracts.store', $customer), $payload + ['total_amount' => 350000])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('contracts', ['customer_id' => $customer->id, 'total_amount' => 350000]);
    }

    // ===== ② ポイント付与一覧の顧客絞り込み =====

    public function test_referral_list_filters_by_customer_name(): void
    {
        $target = $this->customer('絞込 太郎');
        $other = $this->customer('別人 花子');
        app(\App\Services\Referral\ReferralPointService::class)->applyDelta($target, 1000, 'referrer_reward');
        app(\App\Services\Referral\ReferralPointService::class)->applyDelta($other, 2000, 'referrer_reward');

        $this->actingAs($this->admin())
            ->get(route('admin.referral.list', ['name' => '絞込']))
            ->assertInertia(fn ($page) => $page
                ->where('grants.data.0.customer', '絞込 太郎')
                ->where('grants.data', fn ($data) => collect($data)->every(fn ($row) => $row['customer'] === '絞込 太郎'))
                ->where('filters.name', '絞込')
            );
    }

    public function test_referral_list_filters_by_customer_shop(): void
    {
        $otherShop = Shop::create(['name' => '他店', 'is_active' => true]);
        $target = $this->customer('店舗A顧客');
        $other = $this->customer('店舗B顧客', ['shop_id' => $otherShop->id]);
        app(\App\Services\Referral\ReferralPointService::class)->applyDelta($target, 1000, 'referrer_reward');
        app(\App\Services\Referral\ReferralPointService::class)->applyDelta($other, 2000, 'referrer_reward');

        $this->actingAs($this->admin())
            ->get(route('admin.referral.list', ['shop_id' => $this->shop->id]))
            ->assertInertia(fn ($page) => $page
                ->where('grants.data', fn ($data) => collect($data)->every(fn ($row) => $row['customer'] === '店舗A顧客'))
            );
    }

    // ===== ③ お友達紹介ブロック（紹介者変更） =====

    private function makeReferral(Customer $referrer, Customer $referred, string $status): Referral
    {
        return Referral::create([
            'referrer_customer_id' => $referrer->id,
            'referred_customer_id' => $referred->id,
            'referred_line_user_id' => 'U-'.$referred->id,
            'status' => $status,
        ]);
    }

    public function test_customer_show_includes_referred_by(): void
    {
        $referrer = $this->customer('紹介者');
        $referred = $this->customer('被紹介者');
        $this->makeReferral($referrer, $referred, Referral::STATUS_LINKED);

        $this->actingAs($this->admin())
            ->get(route('admin.customers.show', $referred))
            ->assertInertia(fn ($page) => $page
                ->where('referredBy.referrer_id', $referrer->id)
                ->where('referredBy.referrer_name', '紹介者')
                ->where('referredBy.locked', false)
            );
    }

    public function test_update_referred_by_changes_referrer_before_matured(): void
    {
        $referrer = $this->customer('旧紹介者');
        $newReferrer = $this->customer('新紹介者');
        $referred = $this->customer('被紹介者');
        $referral = $this->makeReferral($referrer, $referred, Referral::STATUS_CONTRACTED);

        $this->actingAs($this->admin())
            ->patch(route('admin.customers.referred-by.update', $referred), [
                'referrer_customer_id' => $newReferrer->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('referrals', [
            'id' => $referral->id,
            'referrer_customer_id' => $newReferrer->id,
        ]);
    }

    public function test_update_referred_by_rejected_when_matured(): void
    {
        $referrer = $this->customer('旧紹介者');
        $newReferrer = $this->customer('新紹介者');
        $referred = $this->customer('被紹介者');
        $referral = $this->makeReferral($referrer, $referred, Referral::STATUS_MATURED);

        $this->actingAs($this->admin())
            ->patch(route('admin.customers.referred-by.update', $referred), [
                'referrer_customer_id' => $newReferrer->id,
            ])
            ->assertSessionHasErrors('referred_by');

        $this->assertDatabaseHas('referrals', [
            'id' => $referral->id,
            'referrer_customer_id' => $referrer->id,
        ]);
    }

    public function test_update_referred_by_rejects_self_referral(): void
    {
        $referrer = $this->customer('紹介者');
        $referred = $this->customer('被紹介者');
        $referral = $this->makeReferral($referrer, $referred, Referral::STATUS_LINKED);

        $this->actingAs($this->admin())
            ->patch(route('admin.customers.referred-by.update', $referred), [
                'referrer_customer_id' => $referred->id,
            ])
            ->assertSessionHasErrors('referred_by');

        $this->assertDatabaseHas('referrals', [
            'id' => $referral->id,
            'referrer_customer_id' => $referrer->id,
        ]);
    }
}
