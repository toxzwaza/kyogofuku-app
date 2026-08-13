<?php

namespace Tests\Feature\Referral;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\Event;
use App\Models\Plan;
use App\Models\Referral;
use App\Models\Shop;
use App\Models\User;
use Database\Seeders\ReferralSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * feature/line-referral-enhancement 第2弾:
 * 紹介経由の予約フォーム（line_ref）→ 予約への紹介レコード紐付け・担当者初期値
 */
class ReferralReservationLinkTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferralSettingsSeeder::class);
        $this->shop = Shop::create(['name' => 'テスト店', 'is_active' => true]);
        config(['line.liff.login_channel_id' => '2009633621']);
    }

    private function customer(string $name = '顧客'): Customer
    {
        return Customer::create(['name' => $name, 'shop_id' => $this->shop->id]);
    }

    private function reservationEvent(): Event
    {
        $event = Event::create([
            'slug' => 'ev-'.uniqid('', true),
            'title' => '紹介テストイベント',
            'form_type' => 'reservation',
            'is_public' => true,
        ]);
        $event->shops()->attach($this->shop->id);

        return $event;
    }

    private function referralFor(Customer $referrer, string $lineUserId = 'Ufriend-resv'): Referral
    {
        return Referral::create([
            'referrer_customer_id' => $referrer->id,
            'referred_line_user_id' => $lineUserId,
            'status' => Referral::STATUS_LINKED,
        ]);
    }

    private function basePayload(): array
    {
        Http::fake();

        return [
            'name' => '被紹介 友子',
            'email' => 'friend@example.com',
            'phone' => '090-1111-2222',
            'postal_code' => '700-0001',
            'privacy_agreed' => true,
        ];
    }

    public function test_store_saves_line_referral_and_sets_default_assignee(): void
    {
        $staff = User::factory()->create(['name' => '担当 太郎']);
        $referrer = $this->customer('紹介者');
        $plan = Plan::firstOrCreate(['code' => 'PRT'], ['name' => '振袖', 'is_active' => true]);
        Contract::create([
            'customer_id' => $referrer->id,
            'shop_id' => $this->shop->id,
            'plan_id' => $plan->id,
            'contract_date' => '2026-07-01',
            'kimono_type' => '振袖',
            'total_amount' => 300000,
            'status' => '確定',
            'user_id' => $staff->id,
        ]);
        $referral = $this->referralFor($referrer);
        $event = $this->reservationEvent();

        $this->post(route('event.reserve', $event), $this->basePayload() + [
            'referred_by_name' => '紹介者（LINEの名前から変更）',
            'line_referral_id' => $referral->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('event_reservations', [
            'event_id' => $event->id,
            'name' => '被紹介 友子',
            'referred_by_name' => '紹介者（LINEの名前から変更）',
            'line_referral_id' => $referral->id,
            'admin_assignee' => '担当 太郎',
        ]);
    }

    public function test_store_uses_latest_confirmed_contract_staff(): void
    {
        $oldStaff = User::factory()->create(['name' => '旧担当']);
        $newStaff = User::factory()->create(['name' => '新担当']);
        $referrer = $this->customer('紹介者');
        $plan = Plan::firstOrCreate(['code' => 'PRT'], ['name' => '振袖', 'is_active' => true]);
        foreach ([['2026-01-10', $oldStaff], ['2026-07-20', $newStaff]] as [$date, $staff]) {
            Contract::create([
                'customer_id' => $referrer->id,
                'shop_id' => $this->shop->id,
                'plan_id' => $plan->id,
                'contract_date' => $date,
                'kimono_type' => '振袖',
                'total_amount' => 200000,
                'status' => '確定',
                'user_id' => $staff->id,
            ]);
        }
        $referral = $this->referralFor($referrer);
        $event = $this->reservationEvent();

        $this->post(route('event.reserve', $event), $this->basePayload() + [
            'line_referral_id' => $referral->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('event_reservations', [
            'event_id' => $event->id,
            'admin_assignee' => '新担当',
        ]);
    }

    public function test_store_without_line_referral_keeps_assignee_null(): void
    {
        $event = $this->reservationEvent();

        $this->post(route('event.reserve', $event), $this->basePayload())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('event_reservations', [
            'event_id' => $event->id,
            'line_referral_id' => null,
            'admin_assignee' => null,
        ]);
    }

    public function test_store_rejects_unknown_line_referral_id(): void
    {
        $event = $this->reservationEvent();

        $this->post(route('event.reserve', $event), $this->basePayload() + [
            'line_referral_id' => 999999,
        ])->assertSessionHasErrors('line_referral_id');
    }

    public function test_reserve_redirect_preserves_referral_query_for_non_lp_event(): void
    {
        // LPテンプレ未設定イベントは /reserve → 紹介ページへリダイレクトされるが、
        // 紹介クエリ（line_ref / referred_by_name）は引き継がれる
        $event = $this->reservationEvent();

        $this->get(route('event.reserve.page', ['slug' => $event->slug, 'line_ref' => 6, 'referred_by_name' => '紹介 花子']))
            ->assertRedirect(route('event.show', ['slug' => $event->slug, 'line_ref' => 6, 'referred_by_name' => '紹介 花子']));
    }

    public function test_liff_events_reserve_url_contains_referral_context(): void
    {
        Http::fake([
            '*oauth2/v2.1/verify*' => Http::response(['sub' => 'Ufriend-resv'], 200),
            '*' => Http::response([], 200),
        ]);
        $referrer = $this->customer('紹介 花子');
        $referral = $this->referralFor($referrer);
        $event = Event::create([
            'slug' => 'ev-liff-'.uniqid('', true),
            'title' => '開催中イベント',
            'form_type' => 'reservation',
            'is_public' => true,
            'start_at' => today()->subDay(),
            'end_at' => today()->addDay(),
        ]);
        $event->shops()->attach($this->shop->id);

        $res = $this->postJson(route('line.liff.my-points.data'), ['id_token' => 'tok'])
            ->assertStatus(403);

        $url = (string) $res->json('events.0.reserve_url');
        $this->assertStringContainsString('line_ref='.$referral->id, $url);
        $this->assertStringContainsString('referred_by_name='.urlencode('紹介 花子'), $url);
    }
}
