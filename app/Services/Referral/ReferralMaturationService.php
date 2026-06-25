<?php

namespace App\Services\Referral;

use App\Models\Contract;
use App\Models\CustomerLineContact;
use App\Models\PointLedger;
use App\Models\Referral;
use App\Services\Line\LineMessagingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 紹介1件を成立（matured）させ、紹介者へポイント還元（成約金額×ステージ%）＋ステージ評価、
 * 被紹介者へ固定ポイントを付与する。
 *
 * - 日次バッチ（referral:mature）と、管理画面からの手動反映の両方で共用。
 * - 据置期間の判定は呼び出し側（コマンド）が行う。本サービスは期間を見ずに即時反映する
 *   ため、手動反映では据置1ヶ月を待たずに強制的に確定できる。
 * - idempotent：既に matured のものは二重付与しない。
 */
class ReferralMaturationService
{
    public function __construct(
        private StageEvaluator $stageEvaluator,
        private PointGrantService $pointGrant,
        private LineMessagingService $messaging,
    ) {}

    /**
     * @return array{ok:bool, reason?:string}
     */
    public function mature(Referral $referral): array
    {
        if ($referral->status === Referral::STATUS_MATURED) {
            return ['ok' => false, 'reason' => 'already_matured'];
        }
        if ($referral->status !== Referral::STATUS_CONTRACTED) {
            return ['ok' => false, 'reason' => 'not_contracted'];
        }

        // 契約がキャンセル（論理削除/確定でなくなった）されていれば対象外
        $contract = $referral->contract_id ? Contract::withTrashed()->find($referral->contract_id) : null;
        if (!$contract || $contract->trashed() || $contract->status !== '確定') {
            return ['ok' => false, 'reason' => 'invalid_contract'];
        }

        $referrerLedger = null;
        $referredLedger = null;

        DB::transaction(function () use ($referral, &$referrerLedger, &$referredLedger) {
            $referral->update([
                'status' => Referral::STATUS_MATURED,
                'matured_at' => now(),
            ]);

            // ① 紹介者：ステージ評価（先に更新）→ 現ステージ%でポイント付与
            if ($referral->referrer) {
                $this->stageEvaluator->evaluate($referral->referrer);
                $referrerLedger = $this->pointGrant->grantReferrerReward($referral->fresh(['referrer.stage', 'contract']));
            }

            // ② 被紹介者：固定ポイント
            if ($referral->referred_customer_id) {
                $referredLedger = $this->pointGrant->grantReferredBonus($referral->fresh('referredCustomer'));
            }
        });

        // ③ LINE通知（トランザクション外。失敗してもポイントは確定済み）
        $this->notify($referral, $referrerLedger, $referredLedger);

        return ['ok' => true];
    }

    /**
     * 紹介者・被紹介者にポイント付与をLINEで通知する。
     */
    private function notify(Referral $referral, ?PointLedger $referrerLedger, ?PointLedger $referredLedger): void
    {
        if ($referrerLedger && $referrerLedger->amount > 0) {
            $lineUserId = $this->lineUserIdForCustomer($referral->referrer_customer_id);
            if ($lineUserId) {
                $this->push($lineUserId, "🎉 紹介者特典！\n"
                    .number_format($referrerLedger->amount)."ポイントが付与されました！\n"
                    .'いつもご紹介ありがとうございます。');
            }
        }

        if ($referredLedger && $referredLedger->amount > 0) {
            // 被紹介者は referred_line_user_id を直接保持。無ければ顧客の連携先から解決。
            $lineUserId = $referral->referred_line_user_id
                ?: $this->lineUserIdForCustomer($referral->referred_customer_id);
            if ($lineUserId) {
                $this->push($lineUserId, "🎉 被紹介者特典！\n"
                    .number_format($referredLedger->amount).'ポイントが付与されました！');
            }
        }
    }

    private function lineUserIdForCustomer(?int $customerId): ?string
    {
        if (!$customerId) {
            return null;
        }

        return CustomerLineContact::query()
            ->where('customer_id', $customerId)
            ->whereNotNull('line_user_id')
            ->value('line_user_id');
    }

    private function push(string $lineUserId, string $text): void
    {
        try {
            $this->messaging->pushTextToUser($lineUserId, $text);
        } catch (\Throwable $e) {
            Log::warning('referral maturation push failed', [
                'line_user_id' => $lineUserId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
