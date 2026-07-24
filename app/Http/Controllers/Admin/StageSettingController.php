<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesUiView;
use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerStage;
use App\Models\Referral;
use App\Models\ReferralSetting;
use App\Models\StageSetting;
use App\Services\Referral\StageEvaluator;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StageSettingController extends Controller
{
    use ResolvesUiView;

    public function index()
    {
        $stages = StageSetting::query()
            ->orderBy('min_referrals')
            ->get(['stage', 'min_referrals', 'reward_rate_percent']);

        return Inertia::render($this->viewFor('Admin/Referral/StageSettings'), [
            'stageSettings' => $stages,
            'settings' => [
                'referred_bonus_points' => ReferralSetting::getInt('referred_bonus_points', 10000),
                'gift_card_unit' => ReferralSetting::getInt('gift_card_unit', 500),
                'maturation_months' => ReferralSetting::getInt('maturation_months', 1),
                'referral_expire_months' => ReferralSetting::getInt('referral_expire_months', 6),
                'hirata_point_rate' => ReferralSetting::getFloat('hirata_point_rate', 1),
            ],
        ]);
    }

    public function update(Request $request, StageEvaluator $evaluator)
    {
        $validated = $request->validate([
            'stages' => 'required|array',
            'stages.*.stage' => 'required|in:bronze,silver,gold,platinum',
            'stages.*.min_referrals' => 'required|integer|min:0',
            'stages.*.reward_rate_percent' => 'required|numeric|min:0|max:100',
            'settings.referred_bonus_points' => 'required|integer|min:0',
            'settings.gift_card_unit' => 'required|integer|min:1',
            'settings.maturation_months' => 'required|integer|min:0|max:12',
            'settings.referral_expire_months' => 'required|integer|min:1|max:60',
            'settings.hirata_point_rate' => 'required|numeric|min:0|max:100',
        ]);

        foreach ($validated['stages'] as $row) {
            StageSetting::updateOrCreate(
                ['stage' => $row['stage']],
                ['min_referrals' => $row['min_referrals'], 'reward_rate_percent' => $row['reward_rate_percent']],
            );
        }

        foreach ($validated['settings'] as $key => $value) {
            ReferralSetting::set($key, (string) $value);
        }

        // 閾値変更を即時反映：成立実績のある顧客のステージを再評価する
        // （成立0の顧客は常にブロンズなので対象外）
        Referral::query()
            ->where('status', Referral::STATUS_MATURED)
            ->distinct()
            ->pluck('referrer_customer_id')
            ->filter()
            ->each(function ($customerId) use ($evaluator) {
                $customer = Customer::find($customerId);
                if ($customer) {
                    $evaluator->evaluate($customer);
                }
            });

        return redirect()->route('admin.referral.stage-settings.index')
            ->with('success', 'ステージ設定を保存しました。');
    }
}
