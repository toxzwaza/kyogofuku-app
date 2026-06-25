<?php

namespace Database\Seeders;

use App\Models\ReferralSetting;
use App\Models\StageSetting;
use Illuminate\Database\Seeder;

class ReferralSettingsSeeder extends Seeder
{
    public function run(): void
    {
        // ステージ設定（人数閾値・還元率）— 管理画面で可変
        $stages = [
            ['stage' => 'bronze',   'min_referrals' => 0,  'reward_rate_percent' => 3],
            ['stage' => 'silver',   'min_referrals' => 4,  'reward_rate_percent' => 4],
            ['stage' => 'gold',     'min_referrals' => 7,  'reward_rate_percent' => 5],
            ['stage' => 'platinum', 'min_referrals' => 11, 'reward_rate_percent' => 10],
        ];
        foreach ($stages as $s) {
            StageSetting::updateOrCreate(['stage' => $s['stage']], $s);
        }

        // 各種設定値（初期値）
        $settings = [
            'referred_bonus_points' => '10000',
            'gift_card_unit' => '500',
            'referral_expire_months' => '6',
            'maturation_months' => '1',
            'contract_amount_tax_rate' => '10',
            'point_base_tax_excluded' => '1',
        ];
        foreach ($settings as $key => $value) {
            ReferralSetting::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
