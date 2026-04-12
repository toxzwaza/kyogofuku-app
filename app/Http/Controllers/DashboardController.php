<?php

namespace App\Http\Controllers;

use App\Models\AttendanceRecord;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerLineMessage;
use App\Models\EmailThread;
use App\Models\Event;
use App\Models\EventReservation;
use App\Models\EventTimeslot;
use App\Models\PhotoSlot;
use App\Models\ReservationNote;
use App\Models\Shop;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    /**
     * ダッシュボードを表示
     */
    public function index(Request $request)
    {
        $today = Carbon::today();
        $thisMonthStart = Carbon::now()->startOfMonth();
        $thisMonthEnd = Carbon::now()->endOfMonth();
        $weekStart = Carbon::now()->startOfWeek();
        $weekEnd = Carbon::now()->endOfWeek();
        $nextWeekStart = Carbon::now()->addWeek()->startOfWeek();
        $nextWeekEnd = Carbon::now()->addWeek()->endOfWeek();

        // 基本統計
        $stats = [
            'customers_today' => Customer::whereDate('created_at', $today)->count(),
        ];

        // フォームタイプ別の予約数（キャンセル済みは除外）
        $formTypeStats = [
            'reservation' => EventReservation::where('cancel_flg', false)->whereHas('event', function ($query) {
                $query->where('form_type', 'reservation');
            })->count(),
            'reservation_hakama' => EventReservation::where('cancel_flg', false)->whereHas('event', function ($query) {
                $query->where('form_type', 'reservation_hakama');
            })->count(),
            'document' => EventReservation::where('cancel_flg', false)->whereHas('event', function ($query) {
                $query->where('form_type', 'document');
            })->count(),
            'contact' => EventReservation::where('cancel_flg', false)->whereHas('event', function ($query) {
                $query->where('form_type', 'contact');
            })->count(),
        ];

        // 予約枠の埋まり率
        $timeslots = EventTimeslot::where('is_active', true)->get();
        $totalCapacity = $timeslots->sum('capacity');
        $totalReserved = 0;

        foreach ($timeslots as $timeslot) {
            $reserved = EventReservation::where('cancel_flg', false)
                ->where('reservation_datetime', $timeslot->start_at->format('Y-m-d H:i:s'))
                ->count();
            $totalReserved += $reserved;
        }

        $occupancyRate = $totalCapacity > 0 ? round(($totalReserved / $totalCapacity) * 100, 1) : 0;

        // 過去7日間の予約トレンド
        $trend7Days = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::today()->subDays($i);
            $trend7Days[] = [
                'date' => $date->format('Y-m-d'),
                'label' => $date->format('m/d'),
                'reservation' => EventReservation::where('cancel_flg', false)->whereHas('event', function ($query) {
                    $query->where('form_type', 'reservation');
                })->whereDate('created_at', $date)->count(),
                'reservation_hakama' => EventReservation::where('cancel_flg', false)->whereHas('event', function ($query) {
                    $query->where('form_type', 'reservation_hakama');
                })->whereDate('created_at', $date)->count(),
                'document' => EventReservation::where('cancel_flg', false)->whereHas('event', function ($query) {
                    $query->where('form_type', 'document');
                })->whereDate('created_at', $date)->count(),
                'contact' => EventReservation::where('cancel_flg', false)->whereHas('event', function ($query) {
                    $query->where('form_type', 'contact');
                })->whereDate('created_at', $date)->count(),
            ];
        }

        // 過去30日間の予約トレンド（週単位）
        $trend30Days = [];
        $startDate = Carbon::today()->subDays(29);
        for ($i = 0; $i < 30; $i++) {
            $date = $startDate->copy()->addDays($i);
            $trend30Days[] = [
                'date' => $date->format('Y-m-d'),
                'label' => $date->format('m/d'),
                'count' => EventReservation::where('cancel_flg', false)->whereDate('created_at', $date)->count(),
            ];
        }

        // 最近の予約（キャンセル済みは除外）
        $recentReservations = EventReservation::where('cancel_flg', false)
            ->with('event')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // 最近のメモ
        $recentNotes = ReservationNote::with(['user', 'reservation.event'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // 最近のイベント
        $recentEvents = Event::orderBy('updated_at', 'desc')
            ->limit(5)
            ->get();

        // 絞り込み用イベント一覧（最近の予約・最近のメモのフィルター用。店舗に紐づくイベントのみ選択可能にするため shop_ids を含める）
        $filterEvents = Event::with('shops:id')->orderBy('title')->get(['id', 'title'])->map(fn ($e) => [
            'id' => $e->id,
            'title' => $e->title,
            'shop_ids' => $e->shops->pluck('id')->values()->all(),
        ]);

        // 予約枠が満席に近いイベント（残り枠が20%以下）
        $eventsWithLowCapacity = Event::with(['timeslots', 'reservations'])
            ->where('is_public', true)
            ->whereIn('form_type', Event::TIMESLOT_RESERVATION_FORM_TYPES)
            ->get()
            ->map(function ($event) {
                $timeslots = $event->timeslots()->where('is_active', true)->get();
                $totalCapacity = $timeslots->sum('capacity');
                $totalReserved = 0;

                foreach ($timeslots as $timeslot) {
                    $reserved = EventReservation::where('event_id', $event->id)
                        ->where('cancel_flg', false)
                        ->where('reservation_datetime', $timeslot->start_at->format('Y-m-d H:i:s'))
                        ->count();
                    $totalReserved += $reserved;
                }

                $occupancyRate = $totalCapacity > 0 ? ($totalReserved / $totalCapacity) * 100 : 0;

                return [
                    'event' => $event,
                    'occupancy_rate' => round($occupancyRate, 1),
                    'total_capacity' => $totalCapacity,
                    'total_reserved' => $totalReserved,
                ];
            })
            ->filter(function ($item) {
                return $item['occupancy_rate'] >= 80 && $item['total_capacity'] > 0;
            })
            ->sortByDesc('occupancy_rate')
            ->take(5)
            ->values();

        // 受付終了間近のイベント（7日以内に終了）
        $endingSoonEvents = Event::where('is_public', true)
            ->whereNotNull('end_at')
            ->where('end_at', '>=', Carbon::today())
            ->where('end_at', '<=', Carbon::today()->addDays(7))
            ->orderBy('end_at', 'asc')
            ->limit(5)
            ->get();

        // 未対応の予約（メモが0件、キャンセル済みは除外）
        $unhandledReservations = EventReservation::where('cancel_flg', false)
            ->whereDoesntHave('notes')
            ->with('event')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // フォームタイプ別の詳細統計
        $formTypeDetails = [
            'reservation' => [
                'total' => $formTypeStats['reservation'],
                'by_venue' => EventReservation::where('cancel_flg', false)->whereHas('event', function ($query) {
                    $query->where('form_type', 'reservation');
                })
                    ->whereNotNull('venue_id')
                    ->with('venue')
                    ->get()
                    ->groupBy('venue_id')
                    ->map(function ($reservations) {
                        return [
                            'venue_name' => $reservations->first()->venue->name ?? '不明',
                            'count' => $reservations->count(),
                        ];
                    })
                    ->values(),
            ],
            'reservation_hakama' => [
                'total' => $formTypeStats['reservation_hakama'],
                'by_venue' => EventReservation::where('cancel_flg', false)->whereHas('event', function ($query) {
                    $query->where('form_type', 'reservation_hakama');
                })
                    ->whereNotNull('venue_id')
                    ->with('venue')
                    ->get()
                    ->groupBy('venue_id')
                    ->map(function ($reservations) {
                        return [
                            'venue_name' => $reservations->first()->venue->name ?? '不明',
                            'count' => $reservations->count(),
                        ];
                    })
                    ->values(),
            ],
            'document' => [
                'total' => $formTypeStats['document'],
                'by_method' => EventReservation::where('cancel_flg', false)->whereHas('event', function ($query) {
                    $query->where('form_type', 'document');
                })
                    ->whereNotNull('request_method')
                    ->select('request_method', DB::raw('count(*) as count'))
                    ->groupBy('request_method')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'method' => $item->request_method,
                            'count' => $item->count,
                        ];
                    }),
            ],
            'contact' => [
                'total' => $formTypeStats['contact'],
                'by_response_method' => EventReservation::where('cancel_flg', false)->whereHas('event', function ($query) {
                    $query->where('form_type', 'contact');
                })
                    ->whereNotNull('heard_from')
                    ->select('heard_from', DB::raw('count(*) as count'))
                    ->groupBy('heard_from')
                    ->get()
                    ->map(function ($item) {
                        return [
                            'method' => $item->heard_from,
                            'count' => $item->count,
                        ];
                    }),
            ],
        ];

        // 店舗別の予約数
        $shopStats = Shop::where('is_active', true)
            ->get()
            ->map(function ($shop) {
                $eventIds = $shop->events()->pluck('events.id');
                $reservationCount = EventReservation::where('cancel_flg', false)->whereIn('event_id', $eventIds)->count();

                return [
                    'shop' => $shop,
                    'reservation_count' => $reservationCount,
                ];
            })
            ->filter(function ($item) {
                return $item['reservation_count'] > 0;
            })
            ->sortByDesc('reservation_count')
            ->take(10)
            ->values();

        // スタッフ別のメモ数
        $staffStats = User::withCount('reservationNotes')
            ->orderBy('reservation_notes_count', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($user) {
                return [
                    'user' => $user,
                    'note_count' => $user->reservation_notes_count,
                ];
            });

        // 今週の予約（予約日時が今週、キャンセル済みは除外）
        $thisWeekReservations = EventReservation::where('cancel_flg', false)->whereHas('event', function ($query) {
            $query->whereIn('form_type', Event::TIMESLOT_RESERVATION_FORM_TYPES);
        })
            ->whereNotNull('reservation_datetime')
            ->get()
            ->filter(function ($reservation) use ($weekStart, $weekEnd) {
                if (! $reservation->reservation_datetime) {
                    return false;
                }
                $reservationDate = Carbon::parse($reservation->reservation_datetime)->startOfDay();

                return $reservationDate >= $weekStart && $reservationDate <= $weekEnd;
            })
            ->map(function ($reservation) {
                $reservation->load('event');

                return $reservation;
            })
            ->sortBy('reservation_datetime')
            ->values();

        // 来週の予約（予約日時が来週、キャンセル済みは除外）
        $nextWeekReservations = EventReservation::where('cancel_flg', false)->whereHas('event', function ($query) {
            $query->whereIn('form_type', Event::TIMESLOT_RESERVATION_FORM_TYPES);
        })
            ->whereNotNull('reservation_datetime')
            ->get()
            ->filter(function ($reservation) use ($nextWeekStart, $nextWeekEnd) {
                if (! $reservation->reservation_datetime) {
                    return false;
                }
                $reservationDate = Carbon::parse($reservation->reservation_datetime)->startOfDay();

                return $reservationDate >= $nextWeekStart && $reservationDate <= $nextWeekEnd;
            })
            ->map(function ($reservation) {
                $reservation->load('event');

                return $reservation;
            })
            ->sortBy('reservation_datetime')
            ->values();

        // 流入経路（utm_source）分析データ（event_reservations ベース）
        $bySource = EventReservation::where('cancel_flg', false)
            ->select(DB::raw('COALESCE(utm_source, \'（未計測）\') as source'), DB::raw('count(*) as count'))
            ->groupBy(DB::raw('COALESCE(utm_source, \'（未計測）\')'))
            ->orderByDesc('count')
            ->get()
            ->map(fn ($item) => ['source' => $item->source, 'count' => (int) $item->count])
            ->values()
            ->toArray();

        $publishedEvents = Event::where('is_public', true)->orderBy('title')->get();
        $byEventWithSources = $publishedEvents->map(function ($event) {
            $sources = EventReservation::where('cancel_flg', false)
                ->where('event_id', $event->id)
                ->select(DB::raw('COALESCE(utm_source, \'（未計測）\') as source'), DB::raw('count(*) as count'))
                ->groupBy(DB::raw('COALESCE(utm_source, \'（未計測）\')'))
                ->orderByDesc('count')
                ->get()
                ->map(fn ($item) => ['source' => $item->source, 'count' => (int) $item->count])
                ->values()
                ->toArray();
            $totalCount = array_sum(array_column($sources, 'count'));

            return [
                'event_id' => $event->id,
                'event_title' => $event->title,
                'sources' => $sources,
                'total_count' => $totalCount,
            ];
        })->filter(fn ($item) => $item['total_count'] > 0)->values()->toArray();

        $utmStats = [
            'total_conversion' => EventReservation::where('cancel_flg', false)->count(),
            'by_source' => $bySource,
            'by_event_with_sources' => $byEventWithSources,
        ];

        // 店舗一覧（スケジュール用）
        $shops = Shop::where('is_active', true)->orderBy('name')->get();
        $currentUser = $request->user();
        $userShops = $currentUser ? $currentUser->shops()
            ->where('shops.is_active', true)
            ->select('shops.id', 'shops.name', 'shop_user.main')
            ->orderByDesc('shop_user.main')
            ->orderBy('shops.name')
            ->get() : collect();
        $users = User::orderBy('name')->get(['id', 'name']);

        // ログインユーザー所属店舗の成約「保留」数・前撮り「詳細未決定」数
        $userShopIds = $userShops->pluck('id')->toArray();
        $pendingContractsCount = ! empty($userShopIds)
            ? Contract::where('status', '保留')->whereIn('shop_id', $userShopIds)->count()
            : 0;
        $undecidedPhotoSlotsCount = ! empty($userShopIds)
            ? PhotoSlot::where('details_undecided', true)
                ->whereHas('shops', fn ($q) => $q->whereIn('shops.id', $userShopIds))
                ->count()
            : 0;

        // ── 要対応セクション用データ ──

        // 1. 返信待ちメール（最新メールが顧客からのスレッド = 当店が未返信）
        $pendingEmailReplies = [];
        if (! empty($userShopIds)) {
            $pendingEmailReplies = EmailThread::with([
                'eventReservation.event.shops',
                'emails' => fn ($q) => $q->orderByDesc('created_at'),
            ])
            ->whereHas('emails')
            ->whereHas('eventReservation', function ($q) use ($userShopIds) {
                $q->where('cancel_flg', false)
                  ->whereHas('event.shops', fn ($sq) => $sq->whereIn('shops.id', $userShopIds));
            })
            ->get()
            ->filter(function ($thread) {
                $latestEmail = $thread->emails->first();
                if (! $latestEmail) {
                    return false;
                }
                $reservation = $thread->eventReservation;
                if (! $reservation || ! $reservation->email) {
                    return false;
                }

                return str_contains(strtolower($latestEmail->from ?? ''), strtolower($reservation->email));
            })
            ->map(function ($thread) use ($userShopIds) {
                $latestEmail = $thread->emails->first();
                $reservation = $thread->eventReservation;
                $event = $reservation->event;
                $shopNames = $event->shops->filter(fn ($s) => in_array($s->id, $userShopIds))->pluck('name')->toArray();
                $elapsedHours = (int) Carbon::parse($latestEmail->created_at)->diffInHours(now());
                $cleanSubject = preg_replace('/\[\d+\]\s*/u', '', $thread->subject ?? $latestEmail->subject ?? '');

                return [
                    'reservation_id' => $reservation->id,
                    'customer_name' => $reservation->name,
                    'subject' => trim($cleanSubject) !== '' ? trim($cleanSubject) : '(件名なし)',
                    'event_name' => $event->title ?? '',
                    'shop_names' => $shopNames,
                    'last_customer_email_at' => $latestEmail->created_at->toIso8601String(),
                    'elapsed_hours' => $elapsedHours,
                    'status' => $reservation->status,
                ];
            })
            ->sortByDesc('elapsed_hours')
            ->take(10)
            ->values()
            ->toArray();
        }

        // 2. 要対応予約（ステータスベース: 未対応/確認中/返信待ち）
        $actionRequiredReservations = [];
        if (! empty($userShopIds)) {
            $actionRequiredReservations = EventReservation::where('cancel_flg', false)
                ->whereIn('status', ['未対応', '確認中', '返信待ち'])
                ->whereHas('event.shops', fn ($q) => $q->whereIn('shops.id', $userShopIds))
                ->with(['event.shops'])
                ->orderBy('created_at', 'asc')
                ->limit(15)
                ->get()
                ->map(function ($r) use ($userShopIds) {
                    return [
                        'reservation_id' => $r->id,
                        'customer_name' => $r->name,
                        'event_name' => $r->event->title ?? '',
                        'shop_names' => $r->event->shops->filter(fn ($s) => in_array($s->id, $userShopIds))->pluck('name')->toArray(),
                        'status' => $r->status,
                        'admin_assignee' => $r->admin_assignee,
                        'created_at' => $r->created_at->toIso8601String(),
                        'days_since_created' => (int) Carbon::parse($r->created_at)->diffInDays(now()),
                    ];
                })
                ->toArray();
        }

        // 3. 本日の来店予約
        $todayAppointments = [];
        if (! empty($userShopIds)) {
            $todayAppointments = EventReservation::where('cancel_flg', false)
                ->whereNotNull('reservation_datetime')
                ->whereDate('reservation_datetime', $today)
                ->whereHas('event.shops', fn ($q) => $q->whereIn('shops.id', $userShopIds))
                ->with(['event.shops', 'venue'])
                ->orderBy('reservation_datetime', 'asc')
                ->get()
                ->map(function ($r) use ($userShopIds) {
                    return [
                        'reservation_id' => $r->id,
                        'time' => Carbon::parse($r->reservation_datetime)->format('H:i'),
                        'customer_name' => $r->name,
                        'event_name' => $r->event->title ?? '',
                        'venue_name' => $r->venue->name ?? '',
                        'shop_names' => $r->event->shops->filter(fn ($s) => in_array($s->id, $userShopIds))->pluck('name')->toArray(),
                        'considering_plans' => $r->considering_plans ?? [],
                        'admin_assignee' => $r->admin_assignee,
                        'status' => $r->status,
                    ];
                })
                ->toArray();
        }

        $lineInboundUnreadCount = 0;
        $lineInboundRecentItems = [];
        if (! empty($userShopIds)) {
            $lineInboundUnreadCount = CustomerLineMessage::query()
                ->where('direction', CustomerLineMessage::DIRECTION_INBOUND)
                ->whereNull('admin_read_at')
                ->whereHas('contact', fn ($q) => $q->whereIn('shop_id', $userShopIds))
                ->count();

            $lineInboundRecentItems = CustomerLineMessage::query()
                ->with(['contact' => fn ($q) => $q->select('id', 'customer_id', 'event_reservation_id', 'label', 'shop_id')])
                ->with(['contact.customer' => fn ($q) => $q->select('id', 'name')])
                ->with(['contact.eventReservation' => fn ($q) => $q->select('id', 'name')])
                ->where('direction', CustomerLineMessage::DIRECTION_INBOUND)
                ->whereHas('contact', fn ($q) => $q->whereIn('shop_id', $userShopIds))
                ->orderByDesc('id')
                ->limit(25)
                ->get()
                ->map(function (CustomerLineMessage $m) {
                    $contact = $m->contact;
                    $isReservation = $contact->customer_id === null && $contact->event_reservation_id !== null;
                    $displayName = $isReservation
                        ? ($contact->eventReservation?->name ?? '予約者')
                        : ($contact->customer?->name ?? '顧客');

                    return [
                        'id' => $m->id,
                        'text' => (string) ($m->text ?? ''),
                        'preview' => mb_substr((string) ($m->text ?? ''), 0, 240),
                        'created_at' => $m->created_at?->toIso8601String(),
                        'customer_id' => $contact->customer_id,
                        'reservation_id' => $contact->event_reservation_id,
                        'link_kind' => $isReservation ? 'reservation' : 'customer',
                        'customer_name' => $displayName,
                        'contact_label' => $contact->label ?? 'お客様',
                        'is_unread' => $m->admin_read_at === null,
                    ];
                })
                ->values()
                ->all();
        }

        // 勤怠状態（今日の出勤・休憩中判定）
        $attendanceStatus = [
            'isWorking' => false,
            'isOnBreak' => false,
            'clockInAt' => null,
            'breakStartAt' => null,
            'todayRecord' => null,
        ];
        if ($currentUser) {
            $todayRecord = AttendanceRecord::where('user_id', $currentUser->id)
                ->whereDate('date', $today)
                ->with('breaks')
                ->first();
            if ($todayRecord) {
                $attendanceStatus['todayRecord'] = [
                    'id' => $todayRecord->id,
                    'date' => $todayRecord->date->format('Y-m-d'),
                    'shop_id' => $todayRecord->shop_id,
                    'clock_in_at' => $todayRecord->clock_in_at?->toIso8601String(),
                    'clock_out_at' => $todayRecord->clock_out_at?->toIso8601String(),
                    'status' => $todayRecord->status,
                    'breaks' => $todayRecord->breaks->map(fn ($b) => [
                        'id' => $b->id,
                        'start_at' => $b->start_at?->toIso8601String(),
                        'end_at' => $b->end_at?->toIso8601String(),
                    ])->values()->all(),
                ];
                $attendanceStatus['isWorking'] = $todayRecord->isWorking();
                $attendanceStatus['isOnBreak'] = $todayRecord->isOnBreak();
                $attendanceStatus['clockInAt'] = $todayRecord->clock_in_at?->toIso8601String();
                $activeBreak = $todayRecord->breaks()->whereNull('end_at')->first();
                $attendanceStatus['breakStartAt'] = $activeBreak?->start_at?->toIso8601String();
                $attendanceStatus['cancellableAction'] = $this->getCancellableAction($todayRecord);
            }
        }

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'pendingContractsCount' => $pendingContractsCount,
            'undecidedPhotoSlotsCount' => $undecidedPhotoSlotsCount,
            'pendingEmailReplies' => $pendingEmailReplies,
            'actionRequiredReservations' => $actionRequiredReservations,
            'todayAppointments' => $todayAppointments,
            'lineInboundUnreadCount' => $lineInboundUnreadCount,
            'lineInboundRecentItems' => $lineInboundRecentItems,
            'formTypeStats' => $formTypeStats,
            'occupancyRate' => $occupancyRate,
            'trend7Days' => $trend7Days,
            'trend30Days' => $trend30Days,
            'recentReservations' => $recentReservations,
            'recentNotes' => $recentNotes,
            'recentEvents' => $recentEvents,
            'eventsWithLowCapacity' => $eventsWithLowCapacity,
            'endingSoonEvents' => $endingSoonEvents,
            'unhandledReservations' => $unhandledReservations,
            'formTypeDetails' => $formTypeDetails,
            'shopStats' => $shopStats,
            'staffStats' => $staffStats,
            'thisWeekReservations' => $thisWeekReservations,
            'nextWeekReservations' => $nextWeekReservations,
            'utmStats' => $utmStats,
            'shops' => $shops,
            'userShops' => $userShops,
            'filterEvents' => $filterEvents,
            'users' => $users,
            'currentUser' => $currentUser ? [
                'id' => $currentUser->id,
                'name' => $currentUser->name,
                'canManageAttendance' => $currentUser->canManageAttendance(),
            ] : null,
            'attendanceStatus' => $attendanceStatus,
            'attendanceManualUrl' => config('attendance.manual_url', ''),
            'attendanceManualUrlManager' => config('attendance.manual_url_manager', ''),
        ]);
    }

    /**
     * 取り消し可能なアクションを取得（一番新しいステータスのみ）
     * 優先順位: 休憩中 → 退勤 → 休憩終了 → 出勤
     */
    private function getCancellableAction(AttendanceRecord $record): ?string
    {
        if ($record->isOnBreak()) {
            return 'break_start'; // 休憩開始を取り消す
        }
        if ($record->clock_out_at !== null) {
            return 'clock_out'; // 退勤を取り消す
        }
        $completedBreak = $record->breaks->filter(fn ($b) => $b->end_at !== null)->last();
        if ($completedBreak) {
            return 'break_end'; // 休憩終了を取り消す（最後の休憩）
        }

        return 'clock_in'; // 出勤を取り消す
    }

    /**
     * ダッシュボード用：最近の予約一覧（店舗・イベント絞り込み・axios用）
     */
    public function recentReservations(Request $request)
    {
        $query = EventReservation::where('cancel_flg', false)
            ->with(['event', 'schedule.user', 'statusUpdatedBy'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('event_id')) {
            $query->where('event_id', $request->event_id);
        }

        if ($request->filled('shop_id')) {
            $query->whereHas('event.shops', function ($q) use ($request) {
                $q->where('shops.id', $request->shop_id);
            });
        }

        $limit = min((int) $request->get('limit', 50), 100);
        $items = $query->limit($limit)->get();

        return response()->json($items->map(function ($r) {
            $schedule = $r->schedule;

            return [
                'id' => $r->id,
                'name' => $r->name,
                'created_at' => $r->created_at->format('Y-m-d H:i:s'),
                'reservation_datetime' => $r->reservation_datetime ?? null,
                'status' => $r->status,
                'status_updated_by' => $r->statusUpdatedBy ? ['id' => $r->statusUpdatedBy->id, 'name' => $r->statusUpdatedBy->name] : null,
                'event' => $r->event ? ['id' => $r->event->id, 'title' => $r->event->title] : null,
                'schedule' => $schedule ? [
                    'id' => $schedule->id,
                    'user' => $schedule->user ? ['id' => $schedule->user->id, 'name' => $schedule->user->name] : null,
                ] : null,
            ];
        }));
    }

    /**
     * ダッシュボード用：最近のメモ一覧（テキスト検索・店舗・イベント絞り込み・axios用）
     */
    public function recentNotes(Request $request)
    {
        $query = ReservationNote::with(['user', 'reservation.event', 'reservation.customer'])
            ->orderBy('created_at', 'desc');

        if ($request->filled('keyword')) {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
                $q->where('content', 'like', "%{$keyword}%")
                    ->orWhereHas('user', function ($eq) use ($keyword) {
                        $eq->where('name', 'like', "%{$keyword}%");
                    });
            });
        }

        if ($request->filled('event_id')) {
            $query->whereHas('reservation', function ($q) use ($request) {
                $q->where('event_id', $request->event_id);
            });
        }

        if ($request->filled('shop_id')) {
            $query->whereHas('reservation.event.shops', function ($q) use ($request) {
                $q->where('shops.id', $request->shop_id);
            });
        }

        $limit = min((int) $request->get('limit', 50), 100);
        $items = $query->limit($limit)->get();

        return response()->json($items->map(function ($n) {
            $res = $n->reservation;
            $reservationPayload = null;
            if ($res) {
                $reservationPayload = [
                    'id' => $res->id,
                    'name' => $res->name,
                    'email' => $res->email,
                    'reservation_datetime' => $res->reservation_datetime ?? null,
                    'event' => $res->event ? ['id' => $res->event->id, 'title' => $res->event->title] : null,
                    'customer' => $res->customer ? [
                        'id' => $res->customer->id,
                        'name' => $res->customer->name,
                        'kana' => $res->customer->kana ?? null,
                    ] : null,
                ];
            }

            return [
                'id' => $n->id,
                'content' => $n->content,
                'created_at' => $n->created_at->format('Y-m-d H:i:s'),
                'user' => $n->user ? ['id' => $n->user->id, 'name' => $n->user->name] : null,
                'reservation' => $reservationPayload,
            ];
        }));
    }
}
