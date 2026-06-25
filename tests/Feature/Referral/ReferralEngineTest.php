<?php

namespace Tests\Feature\Referral;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerLineContact;
use App\Models\CustomerStage;
use App\Models\GiftCard;
use App\Models\Plan;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\ReferralPoint;
use App\Models\Shop;
use App\Services\Referral\GiftCardService;
use App\Services\Referral\PointGrantService;
use App\Services\Referral\ReferralLinkingService;
use App\Services\Referral\ReferralMaturationService;
use App\Services\Referral\ReferralPointService;
use App\Services\Referral\StageEvaluator;
use Database\Seeders\ReferralSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * LINE紹介機能 Phase 1（ロジック基盤）のテスト。サービス／コマンドを直接呼んで検証。
 */
class ReferralEngineTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;
    private Plan $plan;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake(); // LINE Push をモック（成立通知の実APIコールを防ぐ）
        $this->seed(ReferralSettingsSeeder::class); // stage_settings / referral_settings
        $this->shop = Shop::create(['name' => 'テスト店', 'is_active' => true]);
        $this->plan = Plan::create(['name' => '振袖', 'code' => 'P'.Str::random(6), 'is_active' => true]);
    }

    private function customer(string $name): Customer
    {
        return Customer::create(['name' => $name, 'shop_id' => $this->shop->id]);
    }

    private function contract(Customer $customer, int $amount, string $status = '確定'): Contract
    {
        return Contract::create([
            'customer_id' => $customer->id,
            'shop_id' => $this->shop->id,
            'plan_id' => $this->plan->id,
            'contract_date' => today(),
            'kimono_type' => '振袖',
            'total_amount' => $amount,
            'status' => $status,
        ]);
    }

    private function lineContact(Customer $customer, string $lineUserId): CustomerLineContact
    {
        return CustomerLineContact::create([
            'customer_id' => $customer->id,
            'shop_id' => $this->shop->id,
            'line_user_id' => $lineUserId,
            'label' => '本人',
        ]);
    }

    private function maturedReferrals(Customer $referrer, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            Referral::create([
                'referrer_customer_id' => $referrer->id,
                'referred_line_user_id' => 'U'.Str::random(10),
                'status' => Referral::STATUS_MATURED,
            ]);
        }
    }

    // ---- 顧客作成で基盤が初期化される ----
    public function test_customer_created_initializes_stage_and_points(): void
    {
        $c = $this->customer('A');
        $this->assertDatabaseHas('customer_stages', ['customer_id' => $c->id, 'stage' => 'bronze']);
        $this->assertDatabaseHas('referral_points', ['customer_id' => $c->id, 'balance' => 0]);
    }

    // ---- StageEvaluator：可変閾値での境界値 ----
    public function test_stage_evaluator_boundaries(): void
    {
        $evaluator = app(StageEvaluator::class);
        $cases = [0 => 'bronze', 3 => 'bronze', 4 => 'silver', 6 => 'silver', 7 => 'gold', 10 => 'gold', 11 => 'platinum', 20 => 'platinum'];
        foreach ($cases as $count => $expected) {
            $referrer = $this->customer("R{$count}");
            $this->maturedReferrals($referrer, $count);
            $result = $evaluator->evaluate($referrer);
            $this->assertSame($expected, $result['stage'], "count={$count}");
            $this->assertDatabaseHas('customer_stages', ['customer_id' => $referrer->id, 'stage' => $expected, 'matured_referrals_count' => $count]);
        }
    }

    // ---- 紹介リンク：成立・自己紹介・既存顧客・重複 ----
    public function test_referral_linking_creates_linked(): void
    {
        $referrer = $this->customer('紹介者');
        $code = ReferralCode::create(['customer_id' => $referrer->id, 'code' => 'ABC12345']);

        $result = app(ReferralLinkingService::class)->link($code->code, 'Ufriend');

        $this->assertSame(Referral::STATUS_LINKED, $result['status']);
        $this->assertDatabaseHas('referrals', [
            'referrer_customer_id' => $referrer->id,
            'referred_line_user_id' => 'Ufriend',
            'status' => 'linked',
        ]);
    }

    public function test_referral_linking_rejects_self(): void
    {
        $referrer = $this->customer('紹介者');
        $this->lineContact($referrer, 'Uself');
        $code = ReferralCode::create(['customer_id' => $referrer->id, 'code' => 'SELF0001']);

        $result = app(ReferralLinkingService::class)->link($code->code, 'Uself');

        $this->assertSame(Referral::STATUS_REJECTED, $result['status']);
        $this->assertSame('self', $result['reason']);
    }

    public function test_referral_linking_rejects_existing_customer(): void
    {
        $referrer = $this->customer('紹介者');
        $other = $this->customer('既存客');
        $this->lineContact($other, 'Uexisting');
        $code = ReferralCode::create(['customer_id' => $referrer->id, 'code' => 'EXIST001']);

        $result = app(ReferralLinkingService::class)->link($code->code, 'Uexisting');

        $this->assertSame(Referral::STATUS_REJECTED, $result['status']);
        $this->assertSame('existing_customer', $result['reason']);
    }

    public function test_referral_linking_returns_existing_on_duplicate(): void
    {
        $referrer = $this->customer('紹介者');
        $code = ReferralCode::create(['customer_id' => $referrer->id, 'code' => 'DUP00001']);
        $svc = app(ReferralLinkingService::class);

        $svc->link($code->code, 'Udup');
        $result = $svc->link($code->code, 'Udup');

        $this->assertSame('duplicate', $result['reason']);
        $this->assertSame(1, Referral::query()->where('referred_line_user_id', 'Udup')->count());
    }

    // ---- 最初の紹介者のみ有効：2人目の紹介者は却下 ----
    public function test_only_first_referrer_is_valid(): void
    {
        $a = $this->customer('紹介者A');
        $b = $this->customer('紹介者B');
        ReferralCode::create(['customer_id' => $a->id, 'code' => 'AAAA1111']);
        ReferralCode::create(['customer_id' => $b->id, 'code' => 'BBBB2222']);
        $svc = app(ReferralLinkingService::class);

        $r1 = $svc->link('AAAA1111', 'Ufirst');
        $this->assertSame(Referral::STATUS_LINKED, $r1['status']);

        $r2 = $svc->link('BBBB2222', 'Ufirst');
        $this->assertSame(Referral::STATUS_REJECTED, $r2['status']);
        $this->assertSame('already_referred', $r2['reason']);
    }

    // ---- LINE連携が後から行われた場合：補完して contracted へ ----
    public function test_late_line_link_backfills_and_contracts(): void
    {
        $referrer = $this->customer('紹介者');
        $referred = $this->customer('被紹介者B');
        // まだ顧客に紐付く前に踏んだ紹介（referred_customer_id は null）
        $referral = Referral::create([
            'referrer_customer_id' => $referrer->id,
            'referred_line_user_id' => 'Ulate',
            'status' => Referral::STATUS_LINKED,
        ]);
        // 成約（この時点ではLINE未連携 → 紐付かず linked のまま）
        $this->contract($referred, 110000);
        $this->assertSame(Referral::STATUS_LINKED, $referral->fresh()->status);

        // 後からLINE連携 → 補完 ＆ contracted へ昇格
        $this->lineContact($referred, 'Ulate');
        $referral->refresh();
        $this->assertSame(Referral::STATUS_CONTRACTED, $referral->status);
        $this->assertSame($referred->id, $referral->referred_customer_id);
    }

    // ---- 複数紹介者の linked を同期で1件に絞る（最初の紹介者のみ有効） ----
    public function test_sync_keeps_only_first_referrer_on_link(): void
    {
        $a = $this->customer('紹介者A');
        $b = $this->customer('紹介者B');
        $referred = $this->customer('被紹介者C');
        $first = Referral::create(['referrer_customer_id' => $a->id, 'referred_line_user_id' => 'Umulti', 'status' => Referral::STATUS_LINKED]);
        $second = Referral::create(['referrer_customer_id' => $b->id, 'referred_line_user_id' => 'Umulti', 'status' => Referral::STATUS_LINKED]);

        $this->lineContact($referred, 'Umulti'); // Observer → sync

        $this->assertSame(Referral::STATUS_LINKED, $first->fresh()->status);
        $this->assertSame(Referral::STATUS_REJECTED, $second->fresh()->status);
        $this->assertSame('already_referred', $second->fresh()->reject_reason);
    }

    // ---- ポイント付与：紹介者（税抜×%）・被紹介者（固定） ----
    public function test_point_grant_referrer_and_referred(): void
    {
        $referrer = $this->customer('紹介者');
        $referred = $this->customer('被紹介者');
        // 紹介者をゴールド（5%）に
        CustomerStage::where('customer_id', $referrer->id)->update(['stage' => 'gold']);

        // 成約金額 税込110,000 → 税抜100,000 × 5% = 5,000pt
        $contract = $this->contract($referred, 110000);
        $referral = Referral::create([
            'referrer_customer_id' => $referrer->id,
            'referred_line_user_id' => 'Ub',
            'referred_customer_id' => $referred->id,
            'status' => Referral::STATUS_MATURED,
            'contract_id' => $contract->id,
        ]);

        $grant = app(PointGrantService::class);
        $grant->grantReferrerReward($referral->fresh(['referrer.stage', 'contract']));
        $grant->grantReferredBonus($referral->fresh('referredCustomer'));

        $this->assertSame(5000, (int) ReferralPoint::where('customer_id', $referrer->id)->value('balance'));
        $this->assertSame(10000, (int) ReferralPoint::where('customer_id', $referred->id)->value('balance'));
        $this->assertDatabaseHas('point_ledger', ['customer_id' => $referrer->id, 'type' => 'referrer_reward', 'amount' => 5000]);
        $this->assertDatabaseHas('point_ledger', ['customer_id' => $referred->id, 'type' => 'referred_bonus', 'amount' => 10000]);
    }

    // ---- ContractObserver：成約で contracted（仮）、matured ではない ----
    public function test_contract_observer_marks_contracted_only(): void
    {
        $referrer = $this->customer('紹介者');
        $referred = $this->customer('被紹介者');
        $referral = Referral::create([
            'referrer_customer_id' => $referrer->id,
            'referred_line_user_id' => 'Ub2',
            'referred_customer_id' => $referred->id,
            'status' => Referral::STATUS_LINKED,
        ]);

        $contract = $this->contract($referred, 100000); // status=確定 → Observer発火

        $referral->refresh();
        $this->assertSame(Referral::STATUS_CONTRACTED, $referral->status);
        $this->assertSame($contract->id, $referral->contract_id);
        $this->assertNotNull($referral->contracted_at);
        // この時点ではポイント未付与
        $this->assertSame(0, (int) ReferralPoint::where('customer_id', $referrer->id)->value('balance'));
    }

    // ---- referral:mature：1ヶ月後に確定＋付与、1ヶ月未満は対象外 ----
    public function test_mature_command_grants_after_one_month(): void
    {
        $referrer = $this->customer('紹介者');
        $referred = $this->customer('被紹介者');
        CustomerStage::where('customer_id', $referrer->id)->update(['stage' => 'bronze']); // 3%
        Referral::create([
            'referrer_customer_id' => $referrer->id,
            'referred_line_user_id' => 'Um1',
            'referred_customer_id' => $referred->id,
            'status' => Referral::STATUS_LINKED,
        ]);
        $contract = $this->contract($referred, 110000); // 税抜100,000 × 3% = 3,000

        // Observerで contracted になる。contracted_at を1ヶ月以上前に偽装
        $referral = Referral::where('referred_customer_id', $referred->id)->first();
        $this->assertSame(Referral::STATUS_CONTRACTED, $referral->status);
        $referral->update(['contracted_at' => now()->subMonths(2)]);

        $this->artisan('referral:mature')->assertSuccessful();

        $referral->refresh();
        $this->assertSame(Referral::STATUS_MATURED, $referral->status);
        $this->assertNotNull($referral->matured_at);
        $this->assertSame(3000, (int) ReferralPoint::where('customer_id', $referrer->id)->value('balance'));
        $this->assertSame(10000, (int) ReferralPoint::where('customer_id', $referred->id)->value('balance'));
    }

    public function test_mature_command_skips_recent_contract(): void
    {
        $referrer = $this->customer('紹介者');
        $referred = $this->customer('被紹介者');
        Referral::create([
            'referrer_customer_id' => $referrer->id,
            'referred_line_user_id' => 'Um2',
            'referred_customer_id' => $referred->id,
            'status' => Referral::STATUS_LINKED,
        ]);
        $this->contract($referred, 110000); // contracted_at = now（1ヶ月未満）

        $this->artisan('referral:mature')->assertSuccessful();

        $referral = Referral::where('referred_customer_id', $referred->id)->first();
        $this->assertSame(Referral::STATUS_CONTRACTED, $referral->status); // matured にならない
        $this->assertSame(0, (int) ReferralPoint::where('customer_id', $referrer->id)->value('balance'));
    }

    // ---- 手動成立（管理画面の「ポイント反映」）：据置を待たず即時付与・冪等 ----
    public function test_manual_maturation_grants_immediately(): void
    {
        $referrer = $this->customer('紹介者');
        $referred = $this->customer('被紹介者');
        Referral::create([
            'referrer_customer_id' => $referrer->id,
            'referred_line_user_id' => 'Umanual',
            'referred_customer_id' => $referred->id,
            'status' => Referral::STATUS_LINKED,
        ]);
        $this->contract($referred, 110000); // Observer → contracted（contracted_at = now、据置未経過）
        $referral = Referral::where('referred_customer_id', $referred->id)->first();
        $this->assertSame(Referral::STATUS_CONTRACTED, $referral->status);

        $svc = app(ReferralMaturationService::class);
        $result = $svc->mature($referral); // 据置を待たず即時反映
        $this->assertTrue($result['ok']);

        $referral->refresh();
        $this->assertSame(Referral::STATUS_MATURED, $referral->status);
        $this->assertSame(3000, (int) ReferralPoint::where('customer_id', $referrer->id)->value('balance'));
        $this->assertSame(10000, (int) ReferralPoint::where('customer_id', $referred->id)->value('balance'));

        // 冪等：2回目は二重付与しない
        $second = $svc->mature($referral->fresh());
        $this->assertFalse($second['ok']);
        $this->assertSame('already_matured', $second['reason']);
        $this->assertSame(3000, (int) ReferralPoint::where('customer_id', $referrer->id)->value('balance'));
    }

    public function test_maturation_pushes_notifications_to_both(): void
    {
        $referrer = $this->customer('紹介者');
        $this->lineContact($referrer, 'UrefBoss'); // 紹介者のLINE
        $referred = $this->customer('被紹介者');
        Referral::create([
            'referrer_customer_id' => $referrer->id,
            'referred_line_user_id' => 'UreferredFriend',
            'referred_customer_id' => $referred->id,
            'status' => Referral::STATUS_LINKED,
        ]);
        $this->contract($referred, 110000); // → contracted
        $referral = Referral::where('referred_customer_id', $referred->id)->first();

        app(ReferralMaturationService::class)->mature($referral);

        // 紹介者へ「紹介者特典」、被紹介者へ「被紹介者特典」のPushが飛ぶ
        Http::assertSent(fn ($req) => str_contains($req->url(), '/v2/bot/message/push')
            && str_contains(json_encode($req->data(), JSON_UNESCAPED_UNICODE), '紹介者特典'));
        Http::assertSent(fn ($req) => str_contains($req->url(), '/v2/bot/message/push')
            && str_contains(json_encode($req->data(), JSON_UNESCAPED_UNICODE), '被紹介者特典'));
    }

    public function test_manual_maturation_rejects_non_contracted(): void
    {
        $referrer = $this->customer('紹介者');
        $referral = Referral::create([
            'referrer_customer_id' => $referrer->id,
            'referred_line_user_id' => 'Ulinkedonly',
            'status' => Referral::STATUS_LINKED,
        ]);

        $result = app(ReferralMaturationService::class)->mature($referral);
        $this->assertFalse($result['ok']);
        $this->assertSame('not_contracted', $result['reason']);
    }

    // ---- ギフトカード：発行（減算）・単位/残高チェック・キャンセル（返還）----
    public function test_gift_card_issue_and_cancel(): void
    {
        $customer = $this->customer('顧客');
        app(ReferralPointService::class)->applyDelta($customer, 2000, 'adjust');

        $svc = app(GiftCardService::class);
        $gc = $svc->issue($customer, 1500, null, $this->shop->id);

        $this->assertSame(GiftCard::STATUS_ISSUED, $gc->status);
        $this->assertSame(500, (int) ReferralPoint::where('customer_id', $customer->id)->value('balance'));
        $this->assertDatabaseHas('point_ledger', ['customer_id' => $customer->id, 'type' => 'gift_card_redeem', 'amount' => -1500]);

        $svc->cancel($gc);
        $this->assertSame(2000, (int) ReferralPoint::where('customer_id', $customer->id)->value('balance'));
        $this->assertDatabaseHas('gift_cards', ['id' => $gc->id, 'status' => 'canceled']);
    }

    public function test_gift_card_rejects_non_unit_and_insufficient(): void
    {
        $customer = $this->customer('顧客');
        app(ReferralPointService::class)->applyDelta($customer, 1000, 'adjust');
        $svc = app(GiftCardService::class);

        // 単位違反（500の倍数でない）
        try {
            $svc->issue($customer, 700);
            $this->fail('ValidationException expected (unit)');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // 残高不足
        try {
            $svc->issue($customer, 1500);
            $this->fail('ValidationException expected (balance)');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
    }
}
