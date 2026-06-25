<?php

namespace App\Services\Referral;

use App\Models\Customer;
use App\Models\CustomerStage;
use App\Models\Referral;
use App\Models\StageSetting;

/**
 * 紹介者の「成立(matured)した紹介数」からステージを判定・更新する。
 */
class StageEvaluator
{
    /**
     * 対象顧客のステージを再評価して更新する。
     *
     * @return array{stage: string, promoted: bool, previous: string, count: int}
     */
    public function evaluate(Customer $customer): array
    {
        $count = Referral::query()
            ->where('referrer_customer_id', $customer->id)
            ->where('status', Referral::STATUS_MATURED)
            ->count();

        $setting = StageSetting::resolveForCount($count);
        $stage = $setting?->stage ?? CustomerStage::STAGE_BRONZE;

        $customerStage = CustomerStage::query()->firstOrCreate(
            ['customer_id' => $customer->id],
            ['stage' => CustomerStage::STAGE_BRONZE, 'matured_referrals_count' => 0],
        );

        $previous = $customerStage->stage;
        $promoted = $this->rank($stage) > $this->rank($previous);

        $customerStage->update([
            'stage' => $stage,
            'matured_referrals_count' => $count,
            'last_evaluated_at' => now(),
        ]);

        return [
            'stage' => $stage,
            'promoted' => $promoted,
            'previous' => $previous,
            'count' => $count,
        ];
    }

    /**
     * 現在の（または指定の）ステージの還元率%を取得
     */
    public function rewardRatePercent(string $stage): float
    {
        $row = StageSetting::query()->where('stage', $stage)->first();

        return (float) ($row?->reward_rate_percent ?? 0);
    }

    private function rank(string $stage): int
    {
        return array_search($stage, CustomerStage::STAGES, true) ?: 0;
    }
}
