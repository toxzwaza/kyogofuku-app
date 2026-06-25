<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\Referral;
use App\Models\ReferralSetting;
use App\Services\Referral\PointGrantService;
use App\Services\Referral\StageEvaluator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 成約から確定期間（既定1ヶ月＝クーリングオフ後）経過した紹介を確定し、
 * 紹介者へポイント還元（成約金額×ステージ%）＋ステージ評価、被紹介者へ固定ポイントを付与する。
 */
class MatureReferrals extends Command
{
    protected $signature = 'referral:mature';

    protected $description = '成約1ヶ月後の紹介を確定し、ポイント付与・ステージ評価を行う';

    public function handle(StageEvaluator $stageEvaluator, PointGrantService $pointGrant): int
    {
        $months = ReferralSetting::getInt('maturation_months', 1);
        $threshold = now()->subMonths($months);

        $targets = Referral::query()
            ->where('status', Referral::STATUS_CONTRACTED)
            ->whereNotNull('contracted_at')
            ->where('contracted_at', '<=', $threshold)
            ->get();

        $matured = 0;
        foreach ($targets as $referral) {
            // 契約がキャンセル（論理削除/確定でなくなった）されていれば対象外
            $contract = $referral->contract_id ? Contract::withTrashed()->find($referral->contract_id) : null;
            if (!$contract || $contract->trashed() || $contract->status !== '確定') {
                continue;
            }

            DB::transaction(function () use ($referral, $stageEvaluator, $pointGrant) {
                $referral->update([
                    'status' => Referral::STATUS_MATURED,
                    'matured_at' => now(),
                ]);

                // ① 紹介者：ステージ評価（先に更新）→ 現ステージ%でポイント付与
                if ($referral->referrer) {
                    $stageEvaluator->evaluate($referral->referrer);
                    $pointGrant->grantReferrerReward($referral->fresh(['referrer.stage', 'contract']));
                }

                // ② 被紹介者：固定ポイント
                if ($referral->referred_customer_id) {
                    $pointGrant->grantReferredBonus($referral->fresh('referredCustomer'));
                }
            });

            $matured++;
        }

        $this->info("確定（matured）: {$matured} 件 / 対象 {$targets->count()} 件");

        return self::SUCCESS;
    }
}
