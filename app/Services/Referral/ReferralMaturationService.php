<?php

namespace App\Services\Referral;

use App\Models\Contract;
use App\Models\Referral;
use Illuminate\Support\Facades\DB;

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

        DB::transaction(function () use ($referral) {
            $referral->update([
                'status' => Referral::STATUS_MATURED,
                'matured_at' => now(),
            ]);

            // ① 紹介者：ステージ評価（先に更新）→ 現ステージ%でポイント付与
            if ($referral->referrer) {
                $this->stageEvaluator->evaluate($referral->referrer);
                $this->pointGrant->grantReferrerReward($referral->fresh(['referrer.stage', 'contract']));
            }

            // ② 被紹介者：固定ポイント
            if ($referral->referred_customer_id) {
                $this->pointGrant->grantReferredBonus($referral->fresh('referredCustomer'));
            }
        });

        return ['ok' => true];
    }
}
