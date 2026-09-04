<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\CustomerLineMessage;
use App\Models\EventReservation;
use App\Models\PhotoType;
use App\Models\StaffSchedule;
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
        $today       = Carbon::today();
        $todayEnd    = $today->copy()->endOfDay();
        $weekStart   = $today->copy()->startOfWeek(Carbon::MONDAY);
        $weekEnd     = $today->copy()->endOfWeek(Carbon::SUNDAY);
        $lastWeekStart = $weekStart->copy()->subWeek();
        $lastWeekEnd   = $weekEnd->copy()->subWeek();

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

        // 本日の予約件数（キャンセル除く）
        $todayCount = EventReservation::whereBetween('reservation_datetime', [$today, $todayEnd])
            ->where('cancel_flg', false)
            ->whereIn('event_id', $shopEventIds)
            ->count();

        // 今週の予約件数
        $thisWeekCount = EventReservation::whereBetween('reservation_datetime', [$weekStart, $weekEnd])
            ->where('cancel_flg', false)
            ->whereIn('event_id', $shopEventIds)
            ->count();
        $lastWeekCount = EventReservation::whereBetween('reservation_datetime', [$lastWeekStart, $lastWeekEnd])
            ->where('cancel_flg', false)
            ->whereIn('event_id', $shopEventIds)
            ->count();
        $weekDelta = $lastWeekCount > 0
            ? round((($thisWeekCount - $lastWeekCount) / $lastWeekCount) * 100, 1)
            : null;

        // キャンセル率（直近30日）
        $since = $today->copy()->subDays(30);
        $totalRecent = EventReservation::where('created_at', '>=', $since)
            ->whereIn('event_id', $shopEventIds)
            ->count();
        $cancelled   = EventReservation::where('created_at', '>=', $since)
            ->where('cancel_flg', true)
            ->whereIn('event_id', $shopEventIds)
            ->count();
        $cancelRate  = $totalRecent > 0 ? round($cancelled / $totalRecent * 100, 1) : 0.0;

        // 要対応件数（ステータス = 確認中・返信待ち・未対応）
        $pendingCount = EventReservation::whereIn('status', ['確認中', '返信待ち', '未対応'])
            ->where('cancel_flg', false)
            ->whereIn('event_id', $shopEventIds)
            ->count();

        // 直近の予約 8件
        $recent = EventReservation::with(['event:id,title', 'venue:id,name'])
            ->where('cancel_flg', false)
            ->whereIn('event_id', $shopEventIds)
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
            ->whereIn('es.shop_id', $userShopIds)
            ->groupBy('s.id', 's.name')
            ->orderByDesc(\DB::raw('COUNT(r.id)'))
            ->limit(6)
            ->get(['s.id', 's.name', \DB::raw('COUNT(r.id) as cnt')]);

        // 日別トレンド（過去14日 → 向こう14日）
        $trendStart = $today->copy()->subDays(13);
        $trendEnd   = $today->copy()->addDays(14);
        $dailyRaw   = \DB::table('event_reservations')
            ->selectRaw('DATE(reservation_datetime) as d, COUNT(*) as cnt')
            ->whereBetween('reservation_datetime', [$trendStart, $trendEnd->copy()->endOfDay()])
            ->where('cancel_flg', false)
            ->whereIn('event_id', $shopEventIds)
            ->groupBy('d')
            ->pluck('cnt', 'd');

        $daily = [];
        $cursor = $trendStart->copy();
        while ($cursor <= $trendEnd) {
            $k = $cursor->format('Y-m-d');
            $daily[] = [
                'date'  => $k,
                'count' => (int) ($dailyRaw[$k] ?? 0),
                'is_past'   => $cursor->lt($today),
                'is_today'  => $cursor->isSameDay($today),
            ];
            $cursor->addDay();
        }

        // ヒートマップ（過去4週間の 曜日×時間帯）
        $heatStart = $today->copy()->subDays(28);
        $heatRows  = \DB::table('event_reservations')
            ->selectRaw('WEEKDAY(reservation_datetime) as dow, HOUR(reservation_datetime) as hr, COUNT(*) as cnt')
            ->whereBetween('reservation_datetime', [$heatStart, $today->copy()->endOfDay()])
            ->where('cancel_flg', false)
            ->whereIn('event_id', $shopEventIds)
            ->groupBy('dow', 'hr')
            ->get();
        // MySQL WEEKDAY: 0=月, 6=日
        $heatmap = [];
        $maxHeat = 0;
        foreach ($heatRows as $r) {
            $heatmap[(int) $r->dow][(int) $r->hr] = (int) $r->cnt;
            if ((int) $r->cnt > $maxHeat) $maxHeat = (int) $r->cnt;
        }

        // ステータス別分布
        $statusDistRaw = EventReservation::where('created_at', '>=', $since)
            ->whereIn('event_id', $shopEventIds)
            ->selectRaw('status, COUNT(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');
        $statusOrder = ['未対応', '確認中', '返信待ち', '対応完了済み', 'キャンセル'];
        $statusDist = [];
        foreach ($statusOrder as $st) {
            $statusDist[] = ['status' => $st, 'count' => (int) ($statusDistRaw[$st] ?? 0)];
        }
        // 規定外ステータスも末尾に
        foreach ($statusDistRaw as $st => $cnt) {
            if (!in_array($st, $statusOrder, true)) {
                $statusDist[] = ['status' => (string) $st, 'count' => (int) $cnt];
            }
        }

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

        // ── LINE 受信（ログインユーザーの担当店舗・お客様単位でグループ化） ──
        // 休業日などに届き未対応のまま残るメッセージを Overview で漏れなく拾うためのブロック。
        // 未読は全件（古い見落としを防ぐ）、既読は直近分のみ取得し、画面側のトグルで
        // 「未読のみ / 既読も表示」を切り替えられるようにする。
        $lineInbound = ['groups' => [], 'unread_total' => 0, 'total' => 0];
        if (! empty($userShopIds)) {
            $contactInShop = fn ($q) => $q->whereIn('shop_id', $userShopIds);
            $withContact = [
                'contact' => fn ($q) => $q->select('id', 'customer_id', 'event_reservation_id', 'label', 'shop_id'),
                'contact.customer' => fn ($q) => $q->select('id', 'name'),
                'contact.eventReservation' => fn ($q) => $q->select('id', 'name'),
            ];

            // 未読は全件（上限300・古い未読も漏らさない）、既読は直近のみ（上限200）
            $unreadMessages = CustomerLineMessage::query()
                ->with($withContact)
                ->where('direction', CustomerLineMessage::DIRECTION_INBOUND)
                ->whereNull('admin_read_at')
                ->whereHas('contact', $contactInShop)
                ->orderByDesc('id')
                ->limit(300)
                ->get();

            $readMessages = CustomerLineMessage::query()
                ->with($withContact)
                ->where('direction', CustomerLineMessage::DIRECTION_INBOUND)
                ->whereNotNull('admin_read_at')
                ->whereHas('contact', $contactInShop)
                ->orderByDesc('id')
                ->limit(200)
                ->get();

            // マージして id 降順（最新が先頭）に整列
            $allMessages = $unreadMessages->concat($readMessages)->sortByDesc('id')->values();

            $groups = [];
            foreach ($allMessages as $m) {
                $contact = $m->contact;
                if (! $contact) {
                    continue;
                }
                $key = $contact->id;
                if (! isset($groups[$key])) {
                    $isReservation = $contact->customer_id === null && $contact->event_reservation_id !== null;
                    $displayName = $isReservation
                        ? ($contact->eventReservation?->name ?? '予約者')
                        : ($contact->customer?->name ?? '顧客');
                    $groups[$key] = [
                        'contact_id'     => $contact->id,
                        'name'           => $displayName,
                        'label'          => $contact->label ?? 'お客様',
                        'link_kind'      => $isReservation ? 'reservation' : 'customer',
                        'customer_id'    => $contact->customer_id,
                        'reservation_id' => $contact->event_reservation_id,
                        'unread_count'   => 0,
                        'total_count'    => 0,
                        'messages'       => [],
                    ];
                }
                $isImage = $m->message_type !== null && $m->message_type !== 'text';
                $isUnread = $m->admin_read_at === null;
                $groups[$key]['messages'][] = [
                    'id'         => $m->id,
                    'text'       => (string) ($m->text ?? ''),
                    'is_image'   => $isImage,
                    'is_unread'  => $isUnread,
                    'created_at' => $m->created_at?->toIso8601String(),
                ];
                $groups[$key]['total_count']++;
                if ($isUnread) {
                    $groups[$key]['unread_count']++;
                }
            }

            // 展開時は時系列（古い順）で読めるように並べ替え
            foreach ($groups as &$g) {
                $g['messages'] = array_reverse($g['messages']);
            }
            unset($g);

            $lineInbound = [
                'groups'       => array_values($groups), // 最新受信のお客様が先頭
                'unread_total' => $unreadMessages->count(),
                'total'        => $allMessages->count(),
            ];
        }

        return Inertia::render($this->viewFor('Admin/Overview'), [
            'stats' => [
                'today_count'     => $todayCount,
                'week_count'      => $thisWeekCount,
                'week_delta'      => $weekDelta,     // 前週比 (%) or null
                'cancel_rate'     => $cancelRate,
                'pending_count'   => $pendingCount,
            ],
            'input_alerts'  => $inputAlerts,
            'user_shop_ids' => array_values($userShopIds),
            'recent_reservations' => $recent,
            'line_inbound'        => $lineInbound,
            'shop_ranking'        => $byShop,
            'week_range'          => [
                'start' => $weekStart->format('Y-m-d'),
                'end'   => $weekEnd->format('Y-m-d'),
            ],
            'daily_trend'   => $daily,        // 過去14日＋今日＋先14日
            'status_dist'   => $statusDist,   // 直近30日のステータス分布
            'heatmap'       => [
                'cells'    => $heatmap,       // [dow][hr] = cnt （dow: 0=月〜6=日）
                'max'      => $maxHeat,
                'period'   => [
                    'start' => $heatStart->format('Y-m-d'),
                    'end'   => $today->format('Y-m-d'),
                ],
            ],
        ]);
    }
}
