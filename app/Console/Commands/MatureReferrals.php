<?php

namespace App\Console\Commands;

use App\Models\Contract;
use App\Models\Referral;
use App\Models\ReferralSetting;
use App\Services\Referral\PointGrantService;
use App\Services\Referral\ReferralMaturationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 成約から確定期間（既定1ヶ月＝クーリングオフ後）経過したものを確定処理する日次バッチ。
 * ①紹介：紹介者へポイント還元（成約金額×ステージ%）＋ステージ評価、被紹介者へ固定ポイント。
 * ②平田ポイント：全成約者へ「成約額(税抜)×平田率」を付与（紹介の有無を問わない）。
 */
class MatureReferrals extends Command
{
    protected $signature = 'referral:mature';

    protected $description = '成約1ヶ月後の紹介確定（紹介ポイント）と、全成約者への平田ポイント付与を行う';

    public function handle(ReferralMaturationService $maturation, PointGrantService $pointGrant): int
    {
        $months = ReferralSetting::getInt('maturation_months', 1);
        $threshold = now()->subMonths($months);

        // ① 紹介の確定（紹介ポイント・ステージ）
        $targets = Referral::query()
            ->where('status', Referral::STATUS_CONTRACTED)
            ->whereNotNull('contracted_at')
            ->where('contracted_at', '<=', $threshold)
            ->get();

        $matured = 0;
        foreach ($targets as $referral) {
            if ($maturation->mature($referral)['ok']) {
                $matured++;
            }
        }

        // ② 平田ポイント：確定検知から確定期間経過・未付与の全成約に付与
        $hirataTargets = Contract::query()
            ->where('status', '確定')
            ->whereNotNull('hirata_eligible_at')
            ->where('hirata_eligible_at', '<=', $threshold)
            ->whereNull('hirata_granted_at')
            ->get();

        $hirataGranted = 0;
        foreach ($hirataTargets as $contract) {
            DB::transaction(function () use ($contract, $pointGrant, &$hirataGranted) {
                $pointGrant->grantHirataForContract($contract);
                $contract->hirata_granted_at = now();
                $contract->saveQuietly();
                $hirataGranted++;
            });
        }

        $this->info("紹介確定: {$matured} 件 / 対象 {$targets->count()} 件、平田ポイント付与: {$hirataGranted} 件 / 対象 {$hirataTargets->count()} 件");

        return self::SUCCESS;
    }
}
