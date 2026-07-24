<?php

namespace Tests\Feature\Referral;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\GiftCard;
use App\Models\Plan;
use App\Models\Referral;
use App\Models\ReferralPoint;
use App\Models\Shop;
use App\Models\StageSetting;
use App\Models\User;
use App\Services\Referral\GiftCardService;
use App\Services\Referral\ReferralPointService;
use App\Services\Referral\StageEvaluator;
use Database\Seeders\ReferralSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ReferralAdminTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(); // LINE Push をモック
        $this->seed(ReferralSettingsSeeder::class);
        $this->shop = Shop::create(['name' => 'テスト店', 'is_active' => true]);
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->shops()->attach($this->shop->id);

        return $user;
    }

    private function customer(string $name = '顧客'): Customer
    {
        return Customer::create(['name' => $name, 'shop_id' => $this->shop->id]);
    }

    public function test_stage_settings_update_saves_thresholds_rates_and_settings(): void
    {
        $payload = [
            'stages' => [
                ['stage' => 'bronze', 'min_referrals' => 0, 'reward_rate_percent' => 2],
                ['stage' => 'silver', 'min_referrals' => 5, 'reward_rate_percent' => 4],
                ['stage' => 'gold', 'min_referrals' => 8, 'reward_rate_percent' => 6],
                ['stage' => 'platinum', 'min_referrals' => 12, 'reward_rate_percent' => 12],
            ],
            'settings' => [
                'referred_bonus_points' => 8000,
                'gift_card_unit' => 1000,
                'maturation_months' => 2,
                'referral_expire_months' => 12,
                'hirata_point_rate' => 1,
            ],
        ];

        $this->actingAs($this->admin())
            ->put(route('admin.referral.stage-settings.update'), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('stage_settings', ['stage' => 'silver', 'min_referrals' => 5, 'reward_rate_percent' => 4]);
        $this->assertDatabaseHas('stage_settings', ['stage' => 'platinum', 'min_referrals' => 12]);
        $this->assertDatabaseHas('referral_settings', ['key' => 'referred_bonus_points', 'value' => '8000']);
        $this->assertDatabaseHas('referral_settings', ['key' => 'gift_card_unit', 'value' => '1000']);
    }

    public function test_gift_card_issue_deducts_points(): void
    {
        $customer = $this->customer();
        app(ReferralPointService::class)->applyDelta($customer, 3000, 'adjust');

        // 交換レート 1pt=0.8円：1,500円 → 必要 ceil(1500/0.8)=1,875pt → 残高 3000-1875=1125
        $this->actingAs($this->admin())
            ->post(route('admin.customers.gift-cards.issue', $customer), ['amount' => 1500])
            ->assertRedirect();

        $this->assertSame(1125, (int) ReferralPoint::where('customer_id', $customer->id)->value('balance'));
        $this->assertDatabaseHas('gift_cards', ['customer_id' => $customer->id, 'amount' => 1500, 'points_spent' => 1875, 'status' => 'issued']);
        $this->assertDatabaseHas('point_ledger', ['customer_id' => $customer->id, 'type' => 'gift_card_redeem', 'amount' => -1875]);
    }

    public function test_gift_card_issue_rejects_insufficient_balance(): void
    {
        $customer = $this->customer();
        app(ReferralPointService::class)->applyDelta($customer, 500, 'adjust');

        $this->actingAs($this->admin())
            ->from(route('admin.customers.show', $customer))
            ->post(route('admin.customers.gift-cards.issue', $customer), ['amount' => 1500])
            ->assertSessionHasErrors('amount');

        $this->assertSame(500, (int) ReferralPoint::where('customer_id', $customer->id)->value('balance'));
    }

    public function test_gift_card_cancel_refunds_points(): void
    {
        $customer = $this->customer();
        app(ReferralPointService::class)->applyDelta($customer, 2000, 'adjust');
        $giftCard = app(GiftCardService::class)->issue($customer, 1000);

        $this->actingAs($this->admin())
            ->post(route('admin.gift-cards.cancel', $giftCard))
            ->assertRedirect();

        $this->assertSame(2000, (int) ReferralPoint::where('customer_id', $customer->id)->value('balance'));
        $this->assertDatabaseHas('gift_cards', ['id' => $giftCard->id, 'status' => 'canceled']);
    }

    public function test_referral_list_renders(): void
    {
        $this->actingAs($this->admin())
            ->get(route('admin.referral.list'))
            ->assertOk();
    }

    public function test_stage_settings_update_reevaluates_existing_customers(): void
    {
        $referrer = $this->customer('紹介者');
        Referral::create([
            'referrer_customer_id' => $referrer->id,
            'referred_line_user_id' => 'Ureeval',
            'status' => Referral::STATUS_MATURED,
        ]);
        // 旧設定（silver=4）では成立1件 → bronze
        app(StageEvaluator::class)->evaluate($referrer);
        $this->assertDatabaseHas('customer_stages', ['customer_id' => $referrer->id, 'stage' => 'bronze']);

        // silver の最小成立を 1 に変更して保存 → 再評価で silver になる
        $payload = [
            'stages' => [
                ['stage' => 'bronze', 'min_referrals' => 0, 'reward_rate_percent' => 3],
                ['stage' => 'silver', 'min_referrals' => 1, 'reward_rate_percent' => 4],
                ['stage' => 'gold', 'min_referrals' => 3, 'reward_rate_percent' => 5],
                ['stage' => 'platinum', 'min_referrals' => 5, 'reward_rate_percent' => 10],
            ],
            'settings' => ['referred_bonus_points' => 10000, 'gift_card_unit' => 500, 'maturation_months' => 1, 'referral_expire_months' => 6, 'hirata_point_rate' => 1],
        ];

        $this->actingAs($this->admin())
            ->put(route('admin.referral.stage-settings.update'), $payload)
            ->assertRedirect();

        $this->assertDatabaseHas('customer_stages', ['customer_id' => $referrer->id, 'stage' => 'silver', 'matured_referrals_count' => 1]);
    }

    public function test_manual_mature_endpoint_grants_points(): void
    {
        $referrer = $this->customer('紹介者');
        $referred = $this->customer('被紹介者');
        $plan = Plan::create(['name' => '振袖', 'code' => 'PX'.uniqid(), 'is_active' => true]);
        // 報酬基準：紹介者本人の成約 税込110,000 → 税抜100,000 × 3%（bronze）= 3,000
        Contract::create([
            'customer_id' => $referrer->id, 'shop_id' => $this->shop->id, 'plan_id' => $plan->id,
            'contract_date' => today(), 'kimono_type' => '振袖', 'total_amount' => 110000, 'status' => '確定',
        ]);
        Referral::create([
            'referrer_customer_id' => $referrer->id,
            'referred_line_user_id' => 'Uadminmature',
            'referred_customer_id' => $referred->id,
            'status' => Referral::STATUS_LINKED,
        ]);
        Contract::create([
            'customer_id' => $referred->id, 'shop_id' => $this->shop->id, 'plan_id' => $plan->id,
            'contract_date' => today(), 'kimono_type' => '振袖', 'total_amount' => 110000, 'status' => '確定',
        ]); // Observer → contracted
        $referral = Referral::where('referred_customer_id', $referred->id)->first();
        $this->assertSame(Referral::STATUS_CONTRACTED, $referral->status);

        $this->actingAs($this->admin())
            ->post(route('admin.referral.mature', $referral))
            ->assertRedirect();

        $this->assertDatabaseHas('referrals', ['id' => $referral->id, 'status' => 'matured']);
        $this->assertSame(3000, (int) ReferralPoint::where('customer_id', $referrer->id)->value('balance'));
        $this->assertSame(10000, (int) ReferralPoint::where('customer_id', $referred->id)->value('balance'));
    }

    public function test_point_purchase_deducts_points(): void
    {
        $customer = $this->customer();
        app(ReferralPointService::class)->applyDelta($customer, 5000, 'adjust');

        // 商品購入で使用（1pt=1円）：2,000pt使用 → 残高3,000
        $this->actingAs($this->admin())
            ->post(route('admin.customers.point-purchase', $customer), ['amount' => 2000, 'note' => '帯'])
            ->assertRedirect();

        $this->assertSame(3000, (int) ReferralPoint::where('customer_id', $customer->id)->value('balance'));
        $this->assertDatabaseHas('point_ledger', ['customer_id' => $customer->id, 'type' => 'product_purchase', 'amount' => -2000]);
    }

    public function test_point_transfer_between_customers(): void
    {
        $from = $this->customer('送り手');
        $to = $this->customer('受け手');
        app(ReferralPointService::class)->applyDelta($from, 3000, 'adjust');

        $this->actingAs($this->admin())
            ->post(route('admin.customers.point-transfers', $from), ['to_customer_id' => $to->id, 'points' => 1000])
            ->assertRedirect();

        $this->assertSame(2000, (int) ReferralPoint::where('customer_id', $from->id)->value('balance'));
        $this->assertSame(1000, (int) ReferralPoint::where('customer_id', $to->id)->value('balance'));
        $this->assertDatabaseHas('point_ledger', ['customer_id' => $from->id, 'type' => 'transfer_out', 'amount' => -1000]);
        $this->assertDatabaseHas('point_ledger', ['customer_id' => $to->id, 'type' => 'transfer_in', 'amount' => 1000]);
    }

    public function test_point_grant_list_shows_referral_and_contract_grants(): void
    {
        // 平田ポイント付与済み（実績・ご成約）
        $c = $this->customer('成約者');
        app(ReferralPointService::class)->applyDelta($c, 1000, \App\Models\PointLedger::TYPE_HIRATA_REWARD, [], \App\Models\PointLedger::POINT_TYPE_HIRATA);

        // 友達紹介：contracted（予定 → 紹介者報酬＋被紹介者特典の2行）
        $referrer = $this->customer('紹介者');
        $referred = $this->customer('被紹介者');
        Referral::create([
            'referrer_customer_id' => $referrer->id,
            'referred_line_user_id' => 'Uplist',
            'referred_customer_id' => $referred->id,
            'status' => Referral::STATUS_CONTRACTED,
            'contracted_at' => now(),
        ]);

        $this->actingAs($this->admin())
            ->get(route('admin.referral.list'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('counts.contract', 1)  // 平田（ご成約）実績1
                ->where('counts.referral', 2)  // 友達紹介 予定2（紹介者報酬＋被紹介者特典）
                ->where('counts.granted', 1)
                ->where('counts.pending', 2)
            );
    }
}
