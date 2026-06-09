<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventReservation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UtmAnalyticsApiController extends Controller
{
    /**
     * UTM 流入経路分析データを返す（GAS 等から計測・分析用に利用）
     * 認証: X-Api-Key ヘッダー または token クエリパラメータで UTM_ANALYTICS_API_SECRET を送信
     *
     * 返却: utm_analytics_enabled=true のイベントのみ。作成日の降順。
     * GAS ではイベント一覧＋予約総数をデフォルト表示し、クリックで utm_source 別の内訳を表示可能。
     */
    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('services.utm_analytics_api_secret');
        if (empty($secret)) {
            return response()->json(['success' => false, 'message' => 'エンドポイントが未設定です'], 503, [], JSON_UNESCAPED_UNICODE);
        }

        $token = $request->header('X-Api-Key') ?? $request->query('token');
        if ($token !== $secret) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401, [], JSON_UNESCAPED_UNICODE);
        }

        // monthly=1 が指定された場合は「直近 months ヶ月の月別集計」を1レスポンスで返す。
        // 指定が無ければ従来通り（全期間 or from_date/to_date 範囲の合算）。
        if ($request->boolean('monthly')) {
            return $this->monthlyResponse($request);
        }

        $baseQuery = EventReservation::where('cancel_flg', false);

        if ($request->has('from_date')) {
            $fromDate = Carbon::parse($request->input('from_date'))->startOfDay();
            $baseQuery->whereDate('created_at', '>=', $fromDate);
        }
        if ($request->has('to_date')) {
            $toDate = Carbon::parse($request->input('to_date'))->endOfDay();
            $baseQuery->whereDate('created_at', '<=', $toDate);
        }

        $events = Event::where('utm_analytics_enabled', true)
            ->orderByRaw('COALESCE(utm_analytics_sort_order, 999999) ASC')
            ->orderByDesc('created_at')
            ->get();

        $eventsData = [];
        foreach ($events as $event) {
            $eventQuery = (clone $baseQuery)->where('event_id', $event->id);
            $totalCount = $eventQuery->count();

            $sources = (clone $eventQuery)
                ->select(DB::raw('COALESCE(utm_source, \'（未計測）\') as source'), DB::raw('count(*) as count'))
                ->groupBy(DB::raw('COALESCE(utm_source, \'（未計測）\')'))
                ->orderByDesc('count')
                ->get()
                ->map(fn($item) => ['source' => $item->source, 'count' => (int) $item->count])
                ->values()
                ->toArray();

            $eventsData[] = [
                'event_id' => $event->id,
                'event_title' => $event->title,
                'total_count' => $totalCount,
                'sources' => $sources,
            ];
        }

        $totalConversion = array_sum(array_column($eventsData, 'total_count'));

        return response()->json([
            'success' => true,
            'total_conversion' => $totalConversion,
            'events' => $eventsData,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 直近 months ヶ月の月別マトリクスを 1 レスポンスで返す。
     *
     * DB アクセスはイベント一覧取得 1 本と、event_id × 年月 × utm_source の
     * 集計 1 本のみ（イベント数・月数に依存せず計 2 クエリ）。
     *
     * app.timezone = Asia/Tokyo・DB 接続の tz 上書き無しのため、
     * created_at は JST のまま保存されており DATE_FORMAT で直接 JST 月に集計できる。
     */
    private function monthlyResponse(Request $request): JsonResponse
    {
        $months = (int) $request->input('months', 6);
        $months = max(1, min(24, $months)); // 1〜24ヶ月にクランプ

        // 古い月 → 新しい月の順でラベル（例: 2026/01 … 2026/06）を作る
        $now = Carbon::now();
        $labels = [];
        $labelIndex = [];
        for ($i = $months - 1; $i >= 0; $i--) {
            $label = $now->copy()->startOfMonth()->subMonths($i)->format('Y/m');
            $labelIndex[$label] = count($labels);
            $labels[] = $label;
        }

        // 集計対象期間：最古月の月初 00:00 〜 翌月月初 00:00（未満）
        $rangeStart = $now->copy()->startOfMonth()->subMonths($months - 1);
        $rangeEnd = $now->copy()->startOfMonth()->addMonth();

        $events = Event::where('utm_analytics_enabled', true)
            ->orderByRaw('COALESCE(utm_analytics_sort_order, 999999) ASC')
            ->orderByDesc('created_at')
            ->get();

        // 表示対象イベントが無ければ空で返す
        if ($events->isEmpty()) {
            return response()->json([
                'success' => true,
                'monthly' => true,
                'months' => $labels,
                'total_conversion' => 0,
                'total_conversion_by_month' => array_fill(0, $months, 0),
                'events' => [],
            ], 200, [], JSON_UNESCAPED_UNICODE);
        }

        // event_id × 年月 × 流入元 を一括集計（クエリ1本）
        $aggregated = EventReservation::where('cancel_flg', false)
            ->whereIn('event_id', $events->pluck('id'))
            ->where('created_at', '>=', $rangeStart)
            ->where('created_at', '<', $rangeEnd)
            ->select(
                'event_id',
                DB::raw("DATE_FORMAT(created_at, '%Y/%m') as ym"),
                DB::raw("COALESCE(utm_source, '（未計測）') as source"),
                DB::raw('count(*) as cnt')
            )
            ->groupBy('event_id', 'ym', DB::raw("COALESCE(utm_source, '（未計測）')"))
            ->get();

        // event_id => source => [月別件数]
        $byEvent = [];
        foreach ($aggregated as $row) {
            $mi = $labelIndex[$row->ym] ?? null;
            if ($mi === null) {
                continue; // 範囲端のズレ等で対象外の月は無視
            }
            $eid = $row->event_id;
            $src = $row->source;
            if (! isset($byEvent[$eid])) {
                $byEvent[$eid] = [];
            }
            if (! isset($byEvent[$eid][$src])) {
                $byEvent[$eid][$src] = array_fill(0, $months, 0);
            }
            $byEvent[$eid][$src][$mi] += (int) $row->cnt;
        }

        $totalByMonth = array_fill(0, $months, 0);
        $eventsData = [];

        foreach ($events as $event) {
            $sourceMap = $byEvent[$event->id] ?? [];

            // 流入元ごとの月別配列＋合計を組み立て
            $sources = [];
            $eventTotalByMonth = array_fill(0, $months, 0);
            foreach ($sourceMap as $src => $counts) {
                $sources[] = [
                    'source' => $src,
                    'count' => array_sum($counts),
                    'count_by_month' => $counts,
                ];
                for ($mi = 0; $mi < $months; $mi++) {
                    $eventTotalByMonth[$mi] += $counts[$mi];
                    $totalByMonth[$mi] += $counts[$mi];
                }
            }

            // 流入元は合計件数の多い順
            usort($sources, fn($a, $b) => $b['count'] <=> $a['count']);

            $eventsData[] = [
                'event_id' => $event->id,
                'event_title' => $event->title,
                'total_count' => array_sum($eventTotalByMonth),
                'total_count_by_month' => $eventTotalByMonth,
                'sources' => $sources,
            ];
        }

        return response()->json([
            'success' => true,
            'monthly' => true,
            'months' => $labels,
            'total_conversion' => array_sum($totalByMonth),
            'total_conversion_by_month' => $totalByMonth,
            'events' => $eventsData,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
