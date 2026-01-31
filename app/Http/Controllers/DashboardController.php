<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventReservation;
use App\Models\EventTimeslot;
use App\Models\EventUtmTracking;
use App\Models\ReservationNote;
use App\Models\Shop;
use App\Models\User;
use App\Models\Customer;
use App\Models\Contract;
use App\Models\PhotoSlot;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

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
            'reservation' => EventReservation::where('cancel_flg', false)->whereHas('event', function($query) {
                $query->where('form_type', 'reservation');
            })->count(),
            'document' => EventReservation::where('cancel_flg', false)->whereHas('event', function($query) {
                $query->where('form_type', 'document');
            })->count(),
            'contact' => EventReservation::where('cancel_flg', false)->whereHas('event', function($query) {
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
                'reservation' => EventReservation::where('cancel_flg', false)->whereHas('event', function($query) {
                    $query->where('form_type', 'reservation');
                })->whereDate('created_at', $date)->count(),
                'document' => EventReservation::where('cancel_flg', false)->whereHas('event', function($query) {
                    $query->where('form_type', 'document');
                })->whereDate('created_at', $date)->count(),
                'contact' => EventReservation::where('cancel_flg', false)->whereHas('event', function($query) {
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

        // 予約枠が満席に近いイベント（残り枠が20%以下）
        $eventsWithLowCapacity = Event::with(['timeslots', 'reservations'])
            ->where('is_public', true)
            ->where('form_type', 'reservation')
            ->get()
            ->map(function($event) {
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
            ->filter(function($item) {
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
                'by_venue' => EventReservation::where('cancel_flg', false)->whereHas('event', function($query) {
                    $query->where('form_type', 'reservation');
                })
                ->whereNotNull('venue_id')
                ->with('venue')
                ->get()
                ->groupBy('venue_id')
                ->map(function($reservations) {
                    return [
                        'venue_name' => $reservations->first()->venue->name ?? '不明',
                        'count' => $reservations->count(),
                    ];
                })
                ->values(),
            ],
            'document' => [
                'total' => $formTypeStats['document'],
                'by_method' => EventReservation::where('cancel_flg', false)->whereHas('event', function($query) {
                    $query->where('form_type', 'document');
                })
                ->whereNotNull('request_method')
                ->select('request_method', DB::raw('count(*) as count'))
                ->groupBy('request_method')
                ->get()
                ->map(function($item) {
                    return [
                        'method' => $item->request_method,
                        'count' => $item->count,
                    ];
                }),
            ],
            'contact' => [
                'total' => $formTypeStats['contact'],
                'by_response_method' => EventReservation::where('cancel_flg', false)->whereHas('event', function($query) {
                    $query->where('form_type', 'contact');
                })
                ->whereNotNull('heard_from')
                ->select('heard_from', DB::raw('count(*) as count'))
                ->groupBy('heard_from')
                ->get()
                ->map(function($item) {
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
            ->map(function($shop) {
                $eventIds = $shop->events()->pluck('events.id');
                $reservationCount = EventReservation::where('cancel_flg', false)->whereIn('event_id', $eventIds)->count();
                
                return [
                    'shop' => $shop,
                    'reservation_count' => $reservationCount,
                ];
            })
            ->filter(function($item) {
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
            ->map(function($user) {
                return [
                    'user' => $user,
                    'note_count' => $user->reservation_notes_count,
                ];
            });

        // 今週の予約（予約日時が今週、キャンセル済みは除外）
        $thisWeekReservations = EventReservation::where('cancel_flg', false)->whereHas('event', function($query) {
            $query->where('form_type', 'reservation');
        })
        ->whereNotNull('reservation_datetime')
        ->get()
        ->filter(function($reservation) use ($weekStart, $weekEnd) {
            if (!$reservation->reservation_datetime) return false;
            $reservationDate = Carbon::parse($reservation->reservation_datetime)->startOfDay();
            return $reservationDate >= $weekStart && $reservationDate <= $weekEnd;
        })
        ->map(function($reservation) {
            $reservation->load('event');
            return $reservation;
        })
        ->sortBy('reservation_datetime')
        ->values();

        // 来週の予約（予約日時が来週、キャンセル済みは除外）
        $nextWeekReservations = EventReservation::where('cancel_flg', false)->whereHas('event', function($query) {
            $query->where('form_type', 'reservation');
        })
        ->whereNotNull('reservation_datetime')
        ->get()
        ->filter(function($reservation) use ($nextWeekStart, $nextWeekEnd) {
            if (!$reservation->reservation_datetime) return false;
            $reservationDate = Carbon::parse($reservation->reservation_datetime)->startOfDay();
            return $reservationDate >= $nextWeekStart && $reservationDate <= $nextWeekEnd;
        })
        ->map(function($reservation) {
            $reservation->load('event');
            return $reservation;
        })
        ->sortBy('reservation_datetime')
        ->values();

        // UTMトラッキング分析データ
        $utmStats = [
            'total_access' => EventUtmTracking::count(),
            'total_conversion' => EventUtmTracking::whereNotNull('event_reservation_id')->count(),
            'by_source' => EventUtmTracking::select('utm_source', DB::raw('count(*) as access_count'))
                ->selectRaw('sum(case when event_reservation_id is not null then 1 else 0 end) as conversion_count')
                ->whereNotNull('utm_source')
                ->groupBy('utm_source')
                ->orderByDesc('access_count')
                ->get()
                ->map(function($item) {
                    return [
                        'source' => $item->utm_source,
                        'access_count' => $item->access_count,
                        'conversion_count' => $item->conversion_count,
                        'conversion_rate' => $item->access_count > 0 ? round(($item->conversion_count / $item->access_count) * 100, 2) : 0,
                    ];
                }),
            'by_medium' => EventUtmTracking::select('utm_medium', DB::raw('count(*) as access_count'))
                ->selectRaw('sum(case when event_reservation_id is not null then 1 else 0 end) as conversion_count')
                ->whereNotNull('utm_medium')
                ->groupBy('utm_medium')
                ->orderByDesc('access_count')
                ->get()
                ->map(function($item) {
                    return [
                        'medium' => $item->utm_medium,
                        'access_count' => $item->access_count,
                        'conversion_count' => $item->conversion_count,
                        'conversion_rate' => $item->access_count > 0 ? round(($item->conversion_count / $item->access_count) * 100, 2) : 0,
                    ];
                }),
            'by_campaign' => EventUtmTracking::select('utm_campaign', DB::raw('count(*) as access_count'))
                ->selectRaw('sum(case when event_reservation_id is not null then 1 else 0 end) as conversion_count')
                ->whereNotNull('utm_campaign')
                ->groupBy('utm_campaign')
                ->orderByDesc('access_count')
                ->get()
                ->map(function($item) {
                    return [
                        'campaign' => $item->utm_campaign,
                        'access_count' => $item->access_count,
                        'conversion_count' => $item->conversion_count,
                        'conversion_rate' => $item->access_count > 0 ? round(($item->conversion_count / $item->access_count) * 100, 2) : 0,
                    ];
                }),
            'by_id' => EventUtmTracking::select('utm_id', DB::raw('count(*) as access_count'))
                ->selectRaw('sum(case when event_reservation_id is not null then 1 else 0 end) as conversion_count')
                ->whereNotNull('utm_id')
                ->groupBy('utm_id')
                ->orderByDesc('access_count')
                ->get()
                ->map(function($item) {
                    return [
                        'id' => $item->utm_id,
                        'access_count' => $item->access_count,
                        'conversion_count' => $item->conversion_count,
                        'conversion_rate' => $item->access_count > 0 ? round(($item->conversion_count / $item->access_count) * 100, 2) : 0,
                    ];
                }),
            'by_event' => EventUtmTracking::with('event')
                ->select('event_id', DB::raw('count(*) as access_count'))
                ->selectRaw('sum(case when event_reservation_id is not null then 1 else 0 end) as conversion_count')
                ->whereNotNull('event_id')
                ->groupBy('event_id')
                ->orderByDesc('access_count')
                ->limit(10)
                ->get()
                ->map(function($item) {
                    return [
                        'event' => $item->event ? [
                            'id' => $item->event->id,
                            'title' => $item->event->title,
                        ] : null,
                        'access_count' => $item->access_count,
                        'conversion_count' => $item->conversion_count,
                        'conversion_rate' => $item->access_count > 0 ? round(($item->conversion_count / $item->access_count) * 100, 2) : 0,
                    ];
                }),
        ];

        $utmStats['overall_conversion_rate'] = $utmStats['total_access'] > 0 
            ? round(($utmStats['total_conversion'] / $utmStats['total_access']) * 100, 2) 
            : 0;

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
        $pendingContractsCount = !empty($userShopIds)
            ? Contract::where('status', '保留')->whereIn('shop_id', $userShopIds)->count()
            : 0;
        $undecidedPhotoSlotsCount = !empty($userShopIds)
            ? PhotoSlot::where('details_undecided', true)
                ->whereHas('shops', fn($q) => $q->whereIn('shops.id', $userShopIds))
                ->count()
            : 0;

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'pendingContractsCount' => $pendingContractsCount,
            'undecidedPhotoSlotsCount' => $undecidedPhotoSlotsCount,
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
            'users' => $users,
            'currentUser' => $currentUser ? [
                'id' => $currentUser->id,
                'name' => $currentUser->name,
            ] : null,
        ]);
    }
}

