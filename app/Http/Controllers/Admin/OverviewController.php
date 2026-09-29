<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CareSuggestion;
use App\Models\Customer;
use App\Models\EventReservation;
use App\Models\PhotoType;
use Carbon\Carbon;
use Illuminate\Http\Request;
use App\Http\Controllers\Concerns\ResolvesUiView;
use Inertia\Inertia;

/**
 * 管理画面モダンダッシュボード（オーバービュー）
 *
 * 既存の /dashboard（勤怠＋予約管理の巨大画面）は温存したまま、
 * 新デザインシステムの足場として軽量 KPI＋最近の予約を並べる画面を提供する。
 */
class OverviewController extends Controller
{
    use ResolvesUiView;

    public function index(Request $request)
    {
        $today = Carbon::today();

        // ログインユーザーの所属店舗（全集計をこの店舗のデータに絞る）
        $currentUser = $request->user();
        $userShopIds = $currentUser
            ? $currentUser->shops()->where('shops.is_active', true)->pluck('shops.id')->toArray()
            : [];

        // 所属店舗に紐づくイベントID。予約系の集計はすべてこの whereIn で絞る
        // （event_shop を join すると1イベント複数店舗で予約が重複カウントされるため）。
        // 所属店舗が空なら whereIn('event_id', []) が0件を返し、全項目ゼロ表示になる。
        $shopEventIds = \DB::table('event_shop')
            ->whereIn('shop_id', $userShopIds)
            ->distinct()
            ->pluck('event_id');

        // 流入経路の集計等で使う直近30日の起点
        $since = $today->copy()->subDays(30);

        // 直近の予約 8件（LINE連携有無・流入導線を含む。ウィジェットと共通のマッパーを使用）
        $recent = OverviewWidgetController::recentReservationsQuery($shopEventIds)
            ->take(8)
            ->get(['id', 'event_id', 'venue_id', 'name', 'furigana', 'reservation_datetime', 'status', 'created_at', 'utm_source'])
            ->map(fn ($r) => OverviewWidgetController::mapReservation($r))
            ->values();

        // 流入経路（utm_source）別の予約者数（直近30日・キャンセル除く）
        $utmDist = EventReservation::where('created_at', '>=', $since)
            ->where('cancel_flg', false)
            ->whereIn('event_id', $shopEventIds)
            ->selectRaw("COALESCE(NULLIF(utm_source, ''), '（直接・不明）') as source, COUNT(*) as cnt")
            ->groupBy('source')
            ->orderByDesc('cnt')
            ->get()
            ->map(fn ($r) => ['source' => (string) $r->source, 'count' => (int) $r->cnt])
            ->values()
            ->all();

        // ── 入力もれチェック（所属店舗の顧客が対象） ──
        // カードのリンク先が顧客一覧のため、レコード件数ではなく「該当顧客数」で数える
        // （カードの数字と一覧の「全N件」を一致させる）。
        $fullBodyTypeId = PhotoType::where('code', 'full_body')->value('id');
        $customerBase = fn () => Customer::whereIn('shop_id', $userShopIds);
        $inputAlerts = [
            // 成約ステータスが「保留」の成約を持つ顧客
            'pending_contracts'       => $customerBase()->whereHas('contracts', fn ($q) => $q->where('status', '保留'))->count(),
            // 詳細未決定の前撮り枠を持つ顧客
            'undecided_photo_slots'   => $customerBase()->whereHas('photoSlots', fn ($q) => $q->where('details_undecided', true))->count(),
            // 顧客写真（全身）が未登録の顧客
            'missing_full_body_photo' => $fullBodyTypeId
                ? $customerBase()->whereDoesntHave('photos', fn ($q) => $q->where('photo_type_id', $fullBodyTypeId))->count()
                : 0,
            // 成約情報が未登録の顧客
            'missing_contract'        => $customerBase()->whereDoesntHave('contracts')->count(),
            // 制約情報が未登録の顧客
            'missing_constraint'      => $customerBase()->whereDoesntHave('constraints')->count(),
        ];

        // ※ LINE受信は全管理画面に常駐するウィジェット（OverviewWidgetController@lineInbox）へ移設。

        // 今日のケアリスト（A9：バッチ care:generate が生成した care_suggestions を読むだけ）
        // care_type ごとに獲得（acquisition＝成約前リード）と維持（retention＝成約後の
        // キャンセル防止フォロー）を分離して渡す。混在させて上位N件で切ると、緊急度の高い
        // 獲得ケアに維持ケアが埋もれて表示されないため。
        $careShopFilter = function ($q) use ($userShopIds) {
            if (! empty($userShopIds)) {
                $q->where(function ($w) use ($userShopIds) {
                    $w->whereIn('shop_id', $userShopIds)->orWhereNull('shop_id');
                });
            }
        };

        $mapCare = fn (CareSuggestion $c) => [
            'id'             => $c->id,
            'name'           => $c->subject_name,
            'score'          => $c->care_score,
            'band'           => $c->priority_band,
            'status'         => $c->reservation_status,
            'days'           => $c->days_since_contact,
            'prospect'       => $c->prospect_label,
            'seijin_year'    => $c->seijin_year,
            'next_action'    => $c->next_action,
            'action_type'    => $c->next_action_type,
            'summary'        => $c->status_summary,
            'reservation_id' => $c->event_reservation_id,
            'customer_id'    => $c->customer_id,
            'assignee'       => $c->assignee,
        ];

        $careListFor = fn (string $careType) => CareSuggestion::query()
            ->where($careShopFilter)
            ->where('care_type', $careType)
            ->orderByRaw("FIELD(priority_band, '緊急', '高', '中', '低')")
            ->orderByDesc('care_score')
            ->limit(20)
            ->get()
            ->map($mapCare);

        $careSummaryFor = fn (string $careType) => CareSuggestion::query()
            ->where($careShopFilter)
            ->where('care_type', $careType)
            ->selectRaw("priority_band, count(*) c")
            ->groupBy('priority_band')
            ->pluck('c', 'priority_band');

        $careList = $careListFor('acquisition');
        $careSummary = $careSummaryFor('acquisition');
        $careListRetention = $careListFor('retention');
        $careSummaryRetention = $careSummaryFor('retention');
        $careGeneratedAt = CareSuggestion::max('generated_at');

        return Inertia::render($this->viewFor('Admin/Overview'), [
            'input_alerts'  => $inputAlerts,
            'care_list'     => $careList,
            'care_summary'  => $careSummary,
            'care_list_retention'    => $careListRetention,
            'care_summary_retention' => $careSummaryRetention,
            'care_generated_at' => $careGeneratedAt,
            'user_shop_ids' => array_values($userShopIds),
            'recent_reservations' => $recent,
            'utm_dist'      => $utmDist,      // 直近30日の流入経路別予約者数（キャンセル除く）
        ]);
    }
}
