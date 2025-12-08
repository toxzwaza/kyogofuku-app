<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventReservation;
use App\Models\EventTimeslot;
use App\Models\ReservationNote;
use App\Models\Shop;
use App\Models\User;
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
            'events' => Event::count(),
            'active_events' => Event::where('is_public', true)
                ->where(function($query) {
                    $query->whereNull('end_at')
                        ->orWhere('end_at', '>=', Carbon::today());
                })
                ->count(),
            'reservations' => EventReservation::count(),
            'reservations_today' => EventReservation::whereDate('created_at', $today)->count(),
            'reservations_this_month' => EventReservation::whereBetween('created_at', [$thisMonthStart, $thisMonthEnd])->count(),
            'shops' => Shop::where('is_active', true)->count(),
            'users' => User::count(),
        ];

        // フォームタイプ別の予約数
        $formTypeStats = [
            'reservation' => EventReservation::whereHas('event', function($query) {
                $query->where('form_type', 'reservation');
            })->count(),
            'document' => EventReservation::whereHas('event', function($query) {
                $query->where('form_type', 'document');
            })->count(),
            'contact' => EventReservation::whereHas('event', function($query) {
                $query->where('form_type', 'contact');
            })->count(),
        ];

        // 予約枠の埋まり率
        $timeslots = EventTimeslot::where('is_active', true)->get();
        $totalCapacity = $timeslots->sum('capacity');
        $totalReserved = 0;
        
        foreach ($timeslots as $timeslot) {
            $reserved = EventReservation::where('reservation_datetime', $timeslot->start_at->format('Y-m-d H:i:s'))
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
                'reservation' => EventReservation::whereHas('event', function($query) {
                    $query->where('form_type', 'reservation');
                })->whereDate('created_at', $date)->count(),
                'document' => EventReservation::whereHas('event', function($query) {
                    $query->where('form_type', 'document');
                })->whereDate('created_at', $date)->count(),
                'contact' => EventReservation::whereHas('event', function($query) {
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
                'count' => EventReservation::whereDate('created_at', $date)->count(),
            ];
        }

        // 最近の予約
        $recentReservations = EventReservation::with('event')
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

        // 未対応の予約（メモが0件）
        $unhandledReservations = EventReservation::whereDoesntHave('notes')
            ->with('event')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // フォームタイプ別の詳細統計
        $formTypeDetails = [
            'reservation' => [
                'total' => $formTypeStats['reservation'],
                'by_venue' => EventReservation::whereHas('event', function($query) {
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
                'by_method' => EventReservation::whereHas('event', function($query) {
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
                'by_response_method' => EventReservation::whereHas('event', function($query) {
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
                $reservationCount = EventReservation::whereIn('event_id', $eventIds)->count();
                
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

        // 今週の予約（予約日時が今週）
        $thisWeekReservations = EventReservation::whereHas('event', function($query) {
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

        // 来週の予約（予約日時が来週）
        $nextWeekReservations = EventReservation::whereHas('event', function($query) {
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

        return Inertia::render('Dashboard', [
            'stats' => $stats,
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
        ]);
    }
}

