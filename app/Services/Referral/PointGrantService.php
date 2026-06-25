<?php

namespace App\Services\Referral;

use App\Models\Contract;
use App\Models\CustomerStage;
use App\Models\PointLedger;
use App\Models\Referral;
use App\Models\ReferralSetting;
use App\Models\StageSetting;

/**
 * 確定した紹介に対するポイント付与。
 * - 紹介者：被紹介者の成約金額 × 紹介者の現ステージ還元率%
 * - 被紹介者：固定ポイント（referral_settings.referred_bonus_points）
 */
class PointGrantService
{
    public function __construct(private ReferralPointService $points)
    {
    }

    public function grantReferrerReward(Referral $referral): ?PointLedger
    {
        $referrer = $referral->referrer;
        $contract = $referral->contract;
        if (!$referrer || !$contract) {
            return null;
        }

        $base = $this->contractBaseAmount($contract);
        $stage = optional($referrer->stage)->stage ?? CustomerStage::STAGE_BRONZE;
        $rate = (float) (StageSetting::query()->where('stage', $stage)->value('reward_rate_percent') ?? 0);

        $points = (int) floor($base * $rate / 100);
        if ($points <= 0) {
            return null;
        }

        return $this->points->applyDelta(
            $referrer,
            $points,
            PointLedger::TYPE_REFERRER_REWARD,
            ['referral_id' => $referral->id, 'note' => "成約{$base}円 × {$rate}%（{$stage}）"],
        );
    }

    public function grantReferredBonus(Referral $referral): ?PointLedger
    {
        $referred = $referral->referredCustomer;
        if (!$referred) {
            return null;
        }

        $points = ReferralSetting::getInt('referred_bonus_points', 10000);
        if ($points <= 0) {
            return null;
        }

        return $this->points->applyDelta(
            $referred,
            $points,
            PointLedger::TYPE_REFERRED_BONUS,
            ['referral_id' => $referral->id, 'note' => '被紹介者特典'],
        );
    }

    /**
     * ポイント計算の基準額。contracts.total_amount は税込のため、設定に応じ税抜へ換算する（C-2）。
     */
    public function contractBaseAmount(Contract $contract): int
    {
        $total = (int) $contract->total_amount;

        if (ReferralSetting::getBool('point_base_tax_excluded', true)) {
            $taxRate = ReferralSetting::getInt('contract_amount_tax_rate', 10);

            return (int) round($total / (1 + $taxRate / 100));
        }

        return $total;
    }
}
