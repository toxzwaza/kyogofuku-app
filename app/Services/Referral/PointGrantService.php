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
 * - 紹介者：紹介者本人の確定成約金額（税抜）の合計 × 紹介者の現ステージ還元率%
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
        if (!$referrer) {
            return null;
        }

        $points = $this->previewReferrerReward($referral);
        if ($points <= 0) {
            return null;
        }

        $totalContract = (int) $referrer->contracts()->where('status', '確定')->sum('total_amount');
        $base = $this->taxExcludedAmount($totalContract);
        $stage = optional($referrer->stage)->stage ?? CustomerStage::STAGE_BRONZE;
        $rate = (float) (StageSetting::query()->where('stage', $stage)->value('reward_rate_percent') ?? 0);

        return $this->points->applyDelta(
            $referrer,
            $points,
            PointLedger::TYPE_REFERRER_REWARD,
            ['referral_id' => $referral->id, 'note' => "紹介者の成約合計{$base}円(税抜) × {$rate}%（{$stage}）"],
        );
    }

    /**
     * 紹介者報酬の予定額を計算する（付与はしない）。紹介者成約合計(税抜) × 現ステージ%。
     */
    public function previewReferrerReward(Referral $referral): int
    {
        $referrer = $referral->referrer;
        if (!$referrer) {
            return 0;
        }

        $totalContract = (int) $referrer->contracts()->where('status', '確定')->sum('total_amount');
        $base = $this->taxExcludedAmount($totalContract);
        if ($base <= 0) {
            return 0;
        }

        $stage = optional($referrer->stage)->stage ?? CustomerStage::STAGE_BRONZE;
        $rate = (float) (StageSetting::query()->where('stage', $stage)->value('reward_rate_percent') ?? 0);

        return (int) floor($base * $rate / 100);
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
     * 平田ポイント：成約者本人へ「成約額(税抜) × 平田ポイント率」を付与（全成約者一律・紹介の有無を問わない）。
     */
    public function grantHirataForContract(Contract $contract): ?PointLedger
    {
        $customer = $contract->customer;
        if (!$customer) {
            return null;
        }

        $points = $this->previewHirata($contract);
        if ($points <= 0) {
            return null;
        }

        $base = $this->taxExcludedAmount((int) $contract->total_amount);
        $rate = ReferralSetting::getFloat('hirata_point_rate', 1);

        return $this->points->applyDelta(
            $customer,
            $points,
            PointLedger::TYPE_HIRATA_REWARD,
            ['note' => "平田ポイント：成約{$base}円(税抜) × {$rate}%"],
            PointLedger::POINT_TYPE_HIRATA,
        );
    }

    /**
     * 平田ポイントの予定額を計算する（付与はしない）。成約額(税抜) × 平田率。
     */
    public function previewHirata(Contract $contract): int
    {
        $base = $this->taxExcludedAmount((int) $contract->total_amount);
        $rate = ReferralSetting::getFloat('hirata_point_rate', 1);

        return (int) floor($base * $rate / 100);
    }

    /**
     * ポイント計算の基準額。contracts.total_amount は税込のため、設定に応じ税抜へ換算する（C-2）。
     */
    public function contractBaseAmount(Contract $contract): int
    {
        return $this->taxExcludedAmount((int) $contract->total_amount);
    }

    /**
     * 税込金額を、設定に応じて税抜へ換算する（C-2）。合計額にも適用可。
     */
    public function taxExcludedAmount(int $total): int
    {
        if (ReferralSetting::getBool('point_base_tax_excluded', true)) {
            $taxRate = ReferralSetting::getInt('contract_amount_tax_rate', 10);

            return (int) round($total / (1 + $taxRate / 100));
        }

        return $total;
    }
}
