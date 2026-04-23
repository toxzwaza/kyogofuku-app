<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EventReservation;
use App\Models\StaffSchedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * 管理画面モダンダッシュボード（オーバービュー）
 *
 * 既存の /dashboard（勤怠＋予約管理の巨大画面）は温存したまま、
 * 新デザインシステムの足場として軽量 KPI＋最近の予約を並べる画面を提供する。
 */
class OverviewController extends Controller
{
    public function index(Request $request)
    {
        $today       = Carbon::today();
        $todayEnd    = $today->copy()->endOfDay();
        $weekStart   = $today->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd     = $today->copy()->endOfWeek(Carbon::SUNDAY);
        $lastWeekStart = $weekStart->copy()->subWeek();
        $lastWeekEnd   = $weekEnd->copy()->subWeek();

        // 本日の予約件数（キャンセル除く）
        $todayCount = EventReservation::whereBetween('reservation_datetime', [$today, $todayEnd])
            ->where('cancel_flg', false)
            ->count();

        // 今週の予約件数
        $thisWeekCount = EventReservation::whereBetween('reservation_datetime', [$weekStart, $weekEnd])
            ->where('cancel_flg', false)
            ->count();
        $lastWeekCount = EventReservation::whereBetween('reservation_datetime', [$lastWeekStart, $lastWeekEnd])
            ->where('cancel_flg', false)
            ->count();
        $weekDelta = $lastWeekCount > 0
            ? round((($thisWeekCount - $lastWeekCount) / $lastWeekCount) * 100, 1)
            : null;

        // キャンセル率（直近30日）
        $since = $today->copy()->subDays(30);
        $totalRecent = EventReservation::where('created_at', '>=', $since)->count();
        $cancelled   = EventReservation::where('created_at', '>=', $since)->where('cancel_flg', true)->count();
        $cancelRate  = $totalRecent > 0 ? round($cancelled / $totalRecent * 100, 1) : 0.0;

        // 要対応件数（ステータス = 確認中・返信待ち・未対応）
        $pendingCount = EventReservation::whereIn('status', ['確認中', '返信待ち', '未対応'])
            ->where('cancel_flg', false)
            ->count();

        // 直近の予約 8件
        $recent = EventReservation::with(['event:id,title', 'venue:id,name'])
            ->where('cancel_flg', false)
            ->orderByDesc('created_at')
            ->take(8)
            ->get(['id', 'event_id', 'venue_id', 'name', 'furigana', 'reservation_datetime', 'status', 'created_at']);

        // 店舗別・今週の予約数（トップ4）
        $byShop = \DB::table('event_reservations as r')
            ->join('events as e', 'r.event_id', '=', 'e.id')
            ->join('event_shop as es', 'e.id', '=', 'es.event_id')
            ->join('shops as s', 'es.shop_id', '=', 's.id')
            ->whereBetween('r.reservation_datetime', [$weekStart, $weekEnd])
            ->where('r.cancel_flg', false)
            ->where('s.is_active', true)
            ->groupBy('s.id', 's.name')
            ->orderByDesc(\DB::raw('COUNT(r.id)'))
            ->limit(6)
            ->get(['s.id', 's.name', \DB::raw('COUNT(r.id) as cnt')]);

        return Inertia::render('Admin/Overview', [
            'stats' => [
                'today_count'     => $todayCount,
                'week_count'      => $thisWeekCount,
                'week_delta'      => $weekDelta,     // 前週比 (%) or null
                'cancel_rate'     => $cancelRate,
                'pending_count'   => $pendingCount,
            ],
            'recent_reservations' => $recent,
            'shop_ranking'        => $byShop,
            'week_range'          => [
                'start' => $weekStart->format('Y-m-d'),
                'end'   => $weekEnd->format('Y-m-d'),
            ],
        ]);
    }
}
