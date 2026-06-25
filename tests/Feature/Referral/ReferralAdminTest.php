<?php

namespace Tests\Feature\Referral;

use App\Models\Customer;
use App\Models\GiftCard;
use App\Models\ReferralPoint;
use App\Models\Shop;
use App\Models\StageSetting;
use App\Models\User;
use App\Services\Referral\GiftCardService;
use App\Services\Referral\ReferralPointService;
use Database\Seeders\ReferralSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReferralAdminTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();
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

        $this->actingAs($this->admin())
            ->post(route('admin.customers.gift-cards.issue', $customer), ['amount' => 1500])
            ->assertRedirect();

        $this->assertSame(1500, (int) ReferralPoint::where('customer_id', $customer->id)->value('balance'));
        $this->assertDatabaseHas('gift_cards', ['customer_id' => $customer->id, 'amount' => 1500, 'status' => 'issued']);
        $this->assertDatabaseHas('point_ledger', ['customer_id' => $customer->id, 'type' => 'gift_card_redeem', 'amount' => -1500]);
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
}
