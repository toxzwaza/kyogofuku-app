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
use App\Services\Referral\PointTransferService;
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

        // 報酬は「紹介者本人の成約合計」が基準：税込110,000 → 税抜100,000 × 5% = 5,000pt
        $this->contract($referrer, 110000);
        $contract = $this->contract($referred, 110000); // maturation トリガー用（referral.contract_id）
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
        $this->contract($referrer, 110000); // 報酬基準：紹介者の成約 税抜100,000 × 3% = 3,000
        Referral::create([
            'referrer_customer_id' => $referrer->id,
            'referred_line_user_id' => 'Um1',
            'referred_customer_id' => $referred->id,
            'status' => Referral::STATUS_LINKED,
        ]);
        $contract = $this->contract($referred, 110000); // maturation トリガー用

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
        $this->contract($referrer, 110000); // 報酬基準：紹介者の成約 税抜100,000 × 3%（bronze）= 3,000
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
        $this->contract($referrer, 110000); // 報酬>0（紹介者特典Pushの条件）
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
        // 交換レート 1pt=0.8円：1,000円 → 必要 ceil(1000/0.8)=1,250pt
        $gc = $svc->issue($customer, 1000, null, $this->shop->id);

        $this->assertSame(GiftCard::STATUS_ISSUED, $gc->status);
        $this->assertSame(1250, (int) $gc->points_spent);
        $this->assertSame(750, (int) ReferralPoint::where('customer_id', $customer->id)->value('balance'));
        $this->assertDatabaseHas('point_ledger', ['customer_id' => $customer->id, 'type' => 'gift_card_redeem', 'amount' => -1250]);

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

    // ---- ポイント譲渡：等価で移動、残高不足・自己譲渡は拒否 ----
    public function test_point_transfer_moves_points_between_customers(): void
    {
        $from = $this->customer('送り手');
        $to = $this->customer('受け手');
        app(ReferralPointService::class)->applyDelta($from, 3000, 'adjust');

        app(PointTransferService::class)->transfer($from, $to, 1000, 'テスト譲渡');

        $this->assertSame(2000, (int) ReferralPoint::where('customer_id', $from->id)->value('balance'));
        $this->assertSame(1000, (int) ReferralPoint::where('customer_id', $to->id)->value('balance'));
        $this->assertDatabaseHas('point_ledger', ['customer_id' => $from->id, 'type' => 'transfer_out', 'amount' => -1000]);
        $this->assertDatabaseHas('point_ledger', ['customer_id' => $to->id, 'type' => 'transfer_in', 'amount' => 1000]);
    }

    public function test_point_transfer_rejects_insufficient_and_self(): void
    {
        $from = $this->customer('送り手');
        $to = $this->customer('受け手');
        app(ReferralPointService::class)->applyDelta($from, 500, 'adjust');

        // 残高不足
        try {
            app(PointTransferService::class)->transfer($from, $to, 1000);
            $this->fail('ValidationException expected (balance)');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // 自己譲渡
        try {
            app(PointTransferService::class)->transfer($from, $from, 100);
            $this->fail('ValidationException expected (self)');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // 残高は変わっていない
        $this->assertSame(500, (int) ReferralPoint::where('customer_id', $from->id)->value('balance'));
    }

    // ---- 平田ポイント：全成約者へ成約額(税抜)×率、確定1ヶ月後バッチ ----
    public function test_hirata_points_granted_to_all_contracted_after_one_month(): void
    {
        // 紹介と無関係の成約顧客（税込110,000 → 税抜100,000 × 1% = 1,000pt）
        $customer = $this->customer('成約者');
        $this->contract($customer, 110000); // ContractObserver が hirata_eligible_at をセット

        // 確定検知日を1ヶ月以上前に偽装
        Contract::where('customer_id', $customer->id)->update(['hirata_eligible_at' => now()->subMonths(2)]);

        $this->artisan('referral:mature')->assertSuccessful();

        $this->assertSame(1000, (int) ReferralPoint::where('customer_id', $customer->id)->value('hirata_balance'));
        // 紹介ポイント（balance）には入らない
        $this->assertSame(0, (int) ReferralPoint::where('customer_id', $customer->id)->value('balance'));
        $this->assertDatabaseHas('point_ledger', ['customer_id' => $customer->id, 'type' => 'hirata_reward', 'point_type' => 'hirata', 'amount' => 1000]);
        $this->assertNotNull(Contract::where('customer_id', $customer->id)->value('hirata_granted_at'));
    }

    public function test_hirata_points_not_granted_before_one_month_and_idempotent(): void
    {
        $customer = $this->customer('成約者');
        $this->contract($customer, 110000); // hirata_eligible_at = now（1ヶ月未満）

        $this->artisan('referral:mature')->assertSuccessful();
        $this->assertSame(0, (int) ReferralPoint::where('customer_id', $customer->id)->value('hirata_balance'));

        // 1ヶ月経過後は付与、再実行しても二重付与しない
        Contract::where('customer_id', $customer->id)->update(['hirata_eligible_at' => now()->subMonths(2)]);
        $this->artisan('referral:mature')->assertSuccessful();
        $this->artisan('referral:mature')->assertSuccessful();
        $this->assertSame(1000, (int) ReferralPoint::where('customer_id', $customer->id)->value('hirata_balance'));
    }

    // ---- 平田ポイントはギフトカードに使えない（紹介ポイントのみ） ----
    public function test_gift_card_cannot_use_hirata_points(): void
    {
        $customer = $this->customer('顧客');
        // 平田ポイントのみ 2,000pt、紹介ポイントは 0
        app(ReferralPointService::class)->applyDelta($customer, 2000, 'adjust', [], \App\Models\PointLedger::POINT_TYPE_HIRATA);

        try {
            app(GiftCardService::class)->issue($customer, 500, null, $this->shop->id); // 必要 625pt（紹介残高0）
            $this->fail('ValidationException expected (gift needs referral points)');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }
        // 平田残高は減っていない
        $this->assertSame(2000, (int) ReferralPoint::where('customer_id', $customer->id)->value('hirata_balance'));
    }

    // ---- 物品購入・譲渡は平田を優先消費、跨る場合は分割 ----
    public function test_purchase_and_transfer_prefer_hirata_points(): void
    {
        $from = $this->customer('顧客');
        app(ReferralPointService::class)->applyDelta($from, 500, 'adjust'); // 紹介 500
        app(ReferralPointService::class)->applyDelta($from, 800, 'adjust', [], \App\Models\PointLedger::POINT_TYPE_HIRATA); // 平田 800

        // 商品購入 1,000pt → 平田800全消費 + 紹介200
        app(ReferralPointService::class)->spendPreferHirata($from, 1000, \App\Models\PointLedger::TYPE_PRODUCT_PURCHASE, 'テスト購入');
        $this->assertSame(0, (int) ReferralPoint::where('customer_id', $from->id)->value('hirata_balance'));
        $this->assertSame(300, (int) ReferralPoint::where('customer_id', $from->id)->value('balance'));

        // 譲渡：残った紹介300のうち200を別顧客へ（平田0なので紹介から）
        $to = $this->customer('受け手');
        app(PointTransferService::class)->transfer($from, $to, 200, null);
        $this->assertSame(100, (int) ReferralPoint::where('customer_id', $from->id)->value('balance'));
        $this->assertSame(200, (int) ReferralPoint::where('customer_id', $to->id)->value('balance'));
    }

    public function test_transfer_preserves_point_type_hirata_first(): void
    {
        $from = $this->customer('送り手');
        $to = $this->customer('受け手');
        app(ReferralPointService::class)->applyDelta($from, 300, 'adjust'); // 紹介300
        app(ReferralPointService::class)->applyDelta($from, 400, 'adjust', [], \App\Models\PointLedger::POINT_TYPE_HIRATA); // 平田400

        // 500譲渡 → 平田400 + 紹介100（受け手も同内訳）
        app(PointTransferService::class)->transfer($from, $to, 500, null);

        $this->assertSame(0, (int) ReferralPoint::where('customer_id', $from->id)->value('hirata_balance'));
        $this->assertSame(200, (int) ReferralPoint::where('customer_id', $from->id)->value('balance'));
        $this->assertSame(400, (int) ReferralPoint::where('customer_id', $to->id)->value('hirata_balance'));
        $this->assertSame(100, (int) ReferralPoint::where('customer_id', $to->id)->value('balance'));
    }
}
