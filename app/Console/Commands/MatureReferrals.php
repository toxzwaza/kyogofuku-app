<?php

namespace App\Console\Commands;

use App\Models\Referral;
use App\Models\ReferralSetting;
use App\Services\Referral\ReferralMaturationService;
use Illuminate\Console\Command;

/**
 * 成約から確定期間（既定1ヶ月＝クーリングオフ後）経過した紹介を確定し、
 * 紹介者へポイント還元（成約金額×ステージ%）＋ステージ評価、被紹介者へ固定ポイントを付与する。
 */
class MatureReferrals extends Command
{
    protected $signature = 'referral:mature';

    protected $description = '成約1ヶ月後の紹介を確定し、ポイント付与・ステージ評価を行う';

    public function handle(ReferralMaturationService $maturation): int
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
            if ($maturation->mature($referral)['ok']) {
                $matured++;
            }
        }

        $this->info("確定（matured）: {$matured} 件 / 対象 {$targets->count()} 件");

        return self::SUCCESS;
    }
}
