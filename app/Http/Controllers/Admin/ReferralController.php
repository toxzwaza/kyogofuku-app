<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesUiView;
use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Services\Referral\ReferralMaturationService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReferralController extends Controller
{
    use ResolvesUiView;

    public function index(Request $request)
    {
        $query = Referral::query()
            ->with(['referrer:id,name', 'referredCustomer:id,name'])
            ->orderByDesc('id');

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        $referrals = $query->paginate(30)->through(fn (Referral $r) => [
            'id' => $r->id,
            'referrer' => $r->referrer?->name,
            'referrer_id' => $r->referrer_customer_id,
            'referred' => $r->referredCustomer?->name,
            'referred_customer_id' => $r->referred_customer_id,
            'referred_line_user_id' => $r->referred_line_user_id,
            'status' => $r->status,
            'reject_reason' => $r->reject_reason,
            'contracted_at' => $r->contracted_at?->format('Y-m-d'),
            'matured_at' => $r->matured_at?->format('Y-m-d'),
            'expires_at' => $r->expires_at?->format('Y-m-d'),
            'created_at' => $r->created_at?->format('Y-m-d'),
        ]);

        // ステータス別件数
        $counts = Referral::query()
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return Inertia::render($this->viewFor('Admin/Referral/Index'), [
            'referrals' => $referrals,
            'counts' => [
                'linked' => (int) ($counts['linked'] ?? 0),
                'contracted' => (int) ($counts['contracted'] ?? 0),
                'matured' => (int) ($counts['matured'] ?? 0),
                'expired' => (int) ($counts['expired'] ?? 0),
                'rejected' => (int) ($counts['rejected'] ?? 0),
            ],
            'filters' => ['status' => $request->input('status', '')],
        ]);
    }

    /**
     * 手動で紹介を成立（matured）させ、ポイントを反映する。
     * 自動バッチ（referral:mature）が動かなかった場合のフォールバック。据置期間は待たず即時反映。
     */
    public function mature(Referral $referral, ReferralMaturationService $maturation)
    {
        $result = $maturation->mature($referral);

        if (!$result['ok']) {
            return back()->with('error', match ($result['reason'] ?? '') {
                'already_matured' => 'この紹介はすでに確定（ポイント反映済み）です。',
                'not_contracted' => 'ポイント反映できるのは「成約（仮）」の紹介のみです。',
                'invalid_contract' => '有効な確定成約が見つからないため反映できません。',
                default => '反映に失敗しました。',
            });
        }

        return back()->with('success', '紹介を成立させ、ポイントを反映しました。');
    }
}
