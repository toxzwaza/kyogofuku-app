<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesUiView;
use App\Http\Controllers\Controller;
use App\Models\Contract;
use App\Models\PointLedger;
use App\Models\Referral;
use App\Services\Referral\PointGrantService;
use App\Services\Referral\ReferralMaturationService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;

class ReferralController extends Controller
{
    use ResolvesUiView;

    /**
     * ポイント付与一覧：友達紹介（紹介者報酬・被紹介者特典）と成約（平田ポイント）の
     * 付与済み（実績）＋付与予定を、対象者別・1付与1行で表示する。
     */
    public function index(Request $request, PointGrantService $grant)
    {
        $kind = (string) $request->input('kind', 'all');     // all / referral / contract
        $status = (string) $request->input('status', 'all'); // all / granted / pending
        $maturationMonths = \App\Models\ReferralSetting::getInt('maturation_months', 1); // 起算日→付与予定日の月数

        $rows = collect();

        // ① 付与済み（point_ledger の付与系プラス取引）
        PointLedger::query()
            ->with('customer:id,name')
            ->whereIn('type', [PointLedger::TYPE_REFERRER_REWARD, PointLedger::TYPE_REFERRED_BONUS, PointLedger::TYPE_HIRATA_REWARD])
            ->orderByDesc('id')
            ->get()
            ->each(function (PointLedger $l) use ($rows) {
                $isHirata = $l->type === PointLedger::TYPE_HIRATA_REWARD;
                $rows->push([
                    'key' => 'ledger-'.$l->id,
                    'kind' => $isHirata ? 'contract' : 'referral',
                    'subtype' => $l->type,
                    'customer' => $l->customer?->name,
                    'customer_id' => $l->customer_id,
                    'points' => (int) $l->amount,
                    'point_type' => $l->point_type,
                    'status' => 'granted',
                    'date' => $l->created_at?->format('Y-m-d'),
                    'scheduled_at' => null,
                    'referral_id' => null,
                ]);
            });

        // ② 付与予定（友達紹介：contracted の紹介 → 紹介者報酬＋被紹介者特典）
        Referral::query()
            ->with(['referrer:id,name', 'referredCustomer:id,name'])
            ->where('status', Referral::STATUS_CONTRACTED)
            ->orderByDesc('id')
            ->get()
            ->each(function (Referral $r) use ($rows, $grant, $maturationMonths) {
                $rows->push([
                    'key' => 'ref-reward-'.$r->id,
                    'kind' => 'referral',
                    'subtype' => PointLedger::TYPE_REFERRER_REWARD,
                    'customer' => $r->referrer?->name,
                    'customer_id' => $r->referrer_customer_id,
                    'points' => $grant->previewReferrerReward($r),
                    'point_type' => PointLedger::POINT_TYPE_REFERRAL,
                    'status' => 'pending',
                    'date' => $r->contracted_at?->format('Y-m-d'),
                    'scheduled_at' => $r->contracted_at?->copy()->addMonths($maturationMonths)->format('Y-m-d'),
                    'referral_id' => $r->id,
                ]);
                $rows->push([
                    'key' => 'ref-bonus-'.$r->id,
                    'kind' => 'referral',
                    'subtype' => PointLedger::TYPE_REFERRED_BONUS,
                    'customer' => $r->referredCustomer?->name,
                    'customer_id' => $r->referred_customer_id,
                    'points' => \App\Models\ReferralSetting::getInt('referred_bonus_points', 10000),
                    'point_type' => PointLedger::POINT_TYPE_REFERRAL,
                    'status' => 'pending',
                    'date' => $r->contracted_at?->format('Y-m-d'),
                    'scheduled_at' => $r->contracted_at?->copy()->addMonths($maturationMonths)->format('Y-m-d'),
                    'referral_id' => $r->id,
                ]);
            });

        // ③ 付与予定（成約：確定・未付与の平田ポイント）
        Contract::query()
            ->with('customer:id,name')
            ->where('status', '確定')
            ->whereNotNull('hirata_eligible_at')
            ->whereNull('hirata_granted_at')
            ->orderByDesc('id')
            ->get()
            ->each(function (Contract $c) use ($rows, $grant, $maturationMonths) {
                $rows->push([
                    'key' => 'hirata-'.$c->id,
                    'kind' => 'contract',
                    'subtype' => PointLedger::TYPE_HIRATA_REWARD,
                    'customer' => $c->customer?->name,
                    'customer_id' => $c->customer_id,
                    'points' => $grant->previewHirata($c),
                    'point_type' => PointLedger::POINT_TYPE_HIRATA,
                    'status' => 'pending',
                    'date' => optional($c->hirata_eligible_at)->format('Y-m-d'),
                    'scheduled_at' => $c->hirata_eligible_at?->copy()->addMonths($maturationMonths)->format('Y-m-d'),
                    'referral_id' => null,
                ]);
            });

        // 件数（フィルタ前の全体）
        $counts = [
            'all' => $rows->count(),
            'referral' => $rows->where('kind', 'referral')->count(),
            'contract' => $rows->where('kind', 'contract')->count(),
            'granted' => $rows->where('status', 'granted')->count(),
            'pending' => $rows->where('status', 'pending')->count(),
        ];

        // フィルタ
        $filtered = $rows
            ->when($kind !== 'all', fn ($c) => $c->where('kind', $kind))
            ->when($status !== 'all', fn ($c) => $c->where('status', $status))
            ->sortByDesc('date')
            ->values();

        // 手動ページネーション（30件）
        $perPage = 30;
        $page = max(1, (int) $request->input('page', 1));
        $paginator = new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return Inertia::render($this->viewFor('Admin/Referral/Index'), [
            'grants' => [
                'data' => $paginator->items(),
                'links' => $paginator->linkCollection(),
                'last_page' => $paginator->lastPage(),
            ],
            'counts' => $counts,
            'filters' => ['kind' => $kind, 'status' => $status],
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
