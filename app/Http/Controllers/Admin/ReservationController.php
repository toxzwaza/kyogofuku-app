<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ReservationReplyMail;
use App\Models\ActivityLog;
use App\Models\Customer;
use App\Models\Email;
use App\Models\EmailAttachment;
use App\Models\EmailThread;
use App\Models\Event;
use App\Models\EventReservation;
use App\Models\EventTimeslot;
use App\Models\ReservationNote;
use App\Models\StaffSchedule;
use App\Models\User;
use App\Services\EventReservationScheduleBootstrapService;
use App\Services\GoogleCalendarSyncService;
use App\Services\Line\EventLineShopResolver;
use App\Services\Line\ReservationLineContactMigrator;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;

class ReservationController extends Controller
{
    /**
     * イベント別予約一覧を表示
     */
    public function index(Request $request, Event $event)
    {
        // 会場リストを取得（会場ID昇順）
        $venues = $event->venues()->where('venues.is_active', true)->orderBy('id')->get();

        // 表示する開始日・終了日（予約フォームの場合のみ）。開始日デフォルトは本日、終了日は未指定で上限なし
        $today = Carbon::today();
        $startDate = $request->filled('start_date')
            ? Carbon::parse($request->start_date)->startOfDay()
            : $today;
        $endDate = $request->filled('end_date')
            ? Carbon::parse($request->end_date)->endOfDay()
            : null;
        $startDateStr = $startDate->format('Y-m-d');
        $endDateStr = $endDate ? $endDate->format('Y-m-d') : null;

        // 予約クエリを作成
        $reservationsQuery = EventReservation::with([
            'venue',
            'statusUpdatedBy',
            'schedule.participantUsers',
            'customer.ceremonyArea',
        ])
            ->where('event_id', $event->id);

        // 予約フォームの場合、開始日〜終了日の範囲で枠に紐づく予約のみ表示
        if ($event->usesTimeslotReservation()) {
            $reservationsQuery->whereDate('reservation_datetime', '>=', $startDateStr);
            if ($endDateStr !== null) {
                $reservationsQuery->whereDate('reservation_datetime', '<=', $endDateStr);
            }
        }

        // 会場で絞り込み
        if ($request->filled('venue_id')) {
            $reservationsQuery->where('venue_id', $request->venue_id);
        }

        // 時間で絞り込み（予約フォームの場合のみ）
        if ($event->usesTimeslotReservation() && $request->filled('reservation_datetime')) {
            $datetime = $request->reservation_datetime;
            // 日付のみ（YYYY-MM-DD形式）の場合は、その日のすべての予約を取得
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $datetime)) {
                $reservationsQuery->whereDate('reservation_datetime', $datetime);
            } else {
                // 日時形式（YYYY-MM-DD HH:MM:SS形式）の場合は完全一致
                $reservationsQuery->where('reservation_datetime', $datetime);
            }
        }

        $reservations = $reservationsQuery
            ->orderBy('cancel_flg', 'asc')
            ->orderBy('reservation_datetime', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()->map(function ($reservation) {
                return [
                    'id' => $reservation->id,
                    'name' => $reservation->name,
                    'email' => $reservation->email,
                    'phone' => $reservation->phone,
                    'venue' => $reservation->venue ? [
                        'id' => $reservation->venue->id,
                        'name' => $reservation->venue->name,
                    ] : null,
                    'furigana' => $reservation->furigana,
                    'has_visited_before' => $reservation->has_visited_before,
                    'address' => $reservation->address,
                    'seijin_year' => $reservation->seijin_year,
                    'school_name' => $reservation->school_name,
                    'parking_usage' => $reservation->parking_usage,
                    'parking_car_count' => $reservation->parking_car_count,
                    'considering_plans' => $reservation->considering_plans,
                    'referred_by_name' => $reservation->referred_by_name,
                    'inquiry_message' => $reservation->inquiry_message,
                    'koichi_furisode_used' => $reservation->koichi_furisode_used,
                    'graduation_ceremony_year' => $reservation->graduation_ceremony_year,
                    'graduation_ceremony_month' => $reservation->graduation_ceremony_month,
                    'graduation_ceremony_date' => $reservation->graduation_ceremony_date?->format('Y-m-d'),
                    'birth_date' => $reservation->birth_date?->format('Y-m-d'),
                    'visitor_count' => $reservation->visitor_count,
                    'status' => $reservation->status,
                    'status_updated_by' => $reservation->statusUpdatedBy ? [
                        'id' => $reservation->statusUpdatedBy->id,
                        'name' => $reservation->statusUpdatedBy->name,
                    ] : null,
                    'reservation_datetime' => $reservation->reservation_datetime,
                    'request_method' => $reservation->request_method,
                    'postal_code' => $reservation->postal_code,
                    'privacy_agreed' => $reservation->privacy_agreed,
                    'heard_from' => $reservation->heard_from,
                    'created_at' => $reservation->created_at->format('Y-m-d H:i:s'),
                    'schedule' => $reservation->schedule ? [
                        'id' => $reservation->schedule->id,
                        'participantUsers' => $reservation->schedule->participantUsers->map(function ($user) {
                            return [
                                'id' => $user->id,
                                'name' => $user->name,
                            ];
                        })->toArray(),
                    ] : null,
                    'admin_assignee' => $reservation->admin_assignee,
                    'entrance_ticket_send_status' => $reservation->entrance_ticket_send_status,
                    'customer_id' => $reservation->customer_id,
                    'ceremony_area_name' => $reservation->customer?->ceremonyArea?->name,
                    'cancel_flg' => $reservation->cancel_flg,
                ];
            });

        // 時間リストを取得（予約フォームの場合のみ、フィルター用）
        $filterTimeslots = [];
        if ($event->usesTimeslotReservation()) {
            $timeslotsQuery = $event->timeslots()->with('venue')->where('is_active', true);

            // 開始日〜終了日の範囲の枠のみ
            $timeslotsQuery->whereDate('start_at', '>=', $startDateStr);
            if ($endDateStr !== null) {
                $timeslotsQuery->whereDate('start_at', '<=', $endDateStr);
            }

            // 会場が選択されている場合、その会場の時間のみ取得
            if ($request->filled('venue_id')) {
                $timeslotsQuery->where('venue_id', $request->venue_id);
            }

            $filterTimeslots = $timeslotsQuery->orderBy('start_at', 'asc')->get()
                ->map(function ($timeslot) {
                    return [
                        'id' => $timeslot->id,
                        'start_at' => $timeslot->start_at->format('Y-m-d H:i:s'),
                        'venue_id' => $timeslot->venue_id,
                    ];
                });
        }

        // 予約枠の統計情報と日付別グループ化（予約フォームの場合のみ）
        $timeslotStats = null;
        $timeslotsWithReservations = null;

        if ($event->usesTimeslotReservation()) {
            $timeslotsQuery = $event->timeslots()->with('venue')->where('is_active', true);

            // 開始日〜終了日の範囲の枠のみ
            $timeslotsQuery->whereDate('start_at', '>=', $startDateStr);
            if ($endDateStr !== null) {
                $timeslotsQuery->whereDate('start_at', '<=', $endDateStr);
            }

            // 会場で絞り込み
            if ($request->filled('venue_id')) {
                $timeslotsQuery->where('venue_id', $request->venue_id);
            }

            // 時間で絞り込み
            if ($request->filled('reservation_datetime')) {
                $datetime = $request->reservation_datetime;
                // 日付のみ（YYYY-MM-DD形式）の場合は、その日のすべての予約枠を取得
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $datetime)) {
                    $timeslotsQuery->whereDate('start_at', $datetime);
                } else {
                    // 日時形式（YYYY-MM-DD HH:MM:SS形式）の場合は完全一致
                    $timeslotsQuery->where('start_at', $datetime);
                }
            }

            $timeslots = $timeslotsQuery->orderBy('start_at', 'asc')->get();
            $totalCapacity = $timeslots->sum('capacity');
            $totalReserved = 0;

            // 各予約枠に予約情報を紐付け
            $timeslotsWithReservations = $timeslots->map(function ($timeslot) use ($event, &$totalReserved) {
                // 予約を取得（会場IDと時間が一致するもののみ、表示用は全件）
                $timeslotReservationsQuery = $event->reservations()
                    ->with([
                        'venue',
                        'statusUpdatedBy',
                        'schedule.participantUsers',
                        'customer.ceremonyArea',
                    ])
                    ->where('reservation_datetime', $timeslot->start_at->format('Y-m-d H:i:s'));

                // 予約枠に会場IDが設定されている場合、同じ会場の予約のみ取得
                if ($timeslot->venue_id) {
                    $timeslotReservationsQuery->where('venue_id', $timeslot->venue_id);
                } else {
                    // 予約枠に会場IDが設定されていない場合、venue_idがnullの予約のみ取得
                    $timeslotReservationsQuery->whereNull('venue_id');
                }

                $timeslotReservations = (clone $timeslotReservationsQuery)
                    ->orderBy('cancel_flg', 'asc')
                    ->orderBy('created_at', 'asc')
                    ->get();

                // 枠数計算はキャンセルされていない予約のみ
                $reservedCount = (clone $timeslotReservationsQuery)->where('cancel_flg', false)->count();
                $totalReserved += $reservedCount;

                return [
                    'id' => $timeslot->id,
                    'venue_id' => $timeslot->venue_id,
                    'venue' => $timeslot->venue ? [
                        'id' => $timeslot->venue->id,
                        'name' => $timeslot->venue->name,
                    ] : null,
                    'start_at' => $timeslot->start_at->format('Y-m-d H:i:s'),
                    'capacity' => $timeslot->capacity,
                    'remaining_capacity' => max(0, $timeslot->capacity - $reservedCount),
                    'reservations' => $timeslotReservations->map(function ($reservation) {
                        return [
                            'id' => $reservation->id,
                            'name' => $reservation->name,
                            'email' => $reservation->email,
                            'phone' => $reservation->phone,
                            'venue' => $reservation->venue ? [
                                'id' => $reservation->venue->id,
                                'name' => $reservation->venue->name,
                            ] : null,
                            'furigana' => $reservation->furigana,
                            'has_visited_before' => $reservation->has_visited_before,
                            'address' => $reservation->address,
                            'birth_date' => $reservation->birth_date,
                            'seijin_year' => $reservation->seijin_year,
                            'school_name' => $reservation->school_name,
                            'parking_usage' => $reservation->parking_usage,
                            'parking_car_count' => $reservation->parking_car_count,
                            'considering_plans' => $reservation->considering_plans,
                            'referred_by_name' => $reservation->referred_by_name,
                            'inquiry_message' => $reservation->inquiry_message,
                            'status' => $reservation->status,
                            'status_updated_by' => $reservation->statusUpdatedBy ? [
                                'id' => $reservation->statusUpdatedBy->id,
                                'name' => $reservation->statusUpdatedBy->name,
                            ] : null,
                            'created_at' => $reservation->created_at->format('Y-m-d H:i:s'),
                            'schedule' => $reservation->schedule ? [
                                'id' => $reservation->schedule->id,
                                'participantUsers' => $reservation->schedule->participantUsers->map(function ($user) {
                                    return [
                                        'id' => $user->id,
                                        'name' => $user->name,
                                    ];
                                })->toArray(),
                            ] : null,
                            'admin_assignee' => $reservation->admin_assignee,
                            'entrance_ticket_send_status' => $reservation->entrance_ticket_send_status,
                            'customer_id' => $reservation->customer_id,
                            'ceremony_area_name' => $reservation->customer?->ceremonyArea?->name,
                            'cancel_flg' => $reservation->cancel_flg,
                        ];
                    })->values(),
                ];
            })->values();

            $timeslotStats = [
                'total_capacity' => $totalCapacity,
                'total_reserved' => $totalReserved,
                'remaining' => max(0, $totalCapacity - $totalReserved),
                'occupancy_rate' => $totalCapacity > 0 ? round(($totalReserved / $totalCapacity) * 100, 1) : 0,
            ];
        }

        return Inertia::render('Admin/Reservation/Index', [
            'event' => $event,
            'reservations' => $reservations,
            'timeslotStats' => $timeslotStats,
            'timeslotsWithReservations' => $timeslotsWithReservations,
            'venues' => $venues->map(function ($venue) {
                return [
                    'id' => $venue->id,
                    'name' => $venue->name,
                ];
            }),
            'filterTimeslots' => $filterTimeslots,
            'filters' => [
                'venue_id' => $request->venue_id ?? null,
                'reservation_datetime' => $request->reservation_datetime ?? null,
                'start_date' => $startDateStr,
                'end_date' => $endDateStr,
            ],
        ]);
    }

    /**
     * 顧客から直接予約を作成（管理画面専用）
     */
    public function storeFromCustomer(Request $request, Event $event)
    {
        $validated = $request->validate([
            'timeslot_id' => 'required|exists:event_timeslots,id',
            'customer_id' => 'required|exists:customers,id',
            'email' => 'required|email|max:255',
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);
        $timeslot = EventTimeslot::where('event_id', $event->id)
            ->where('id', $validated['timeslot_id'])
            ->where('is_active', true)
            ->first();

        if (! $timeslot) {
            return redirect()
                ->route('admin.events.reservations.index', $event->id)
                ->with('error', '選択された予約枠が見つかりません。');
        }

        // 残枠チェック
        $reservationCountQuery = EventReservation::where('event_id', $event->id)
            ->where('cancel_flg', false)
            ->where('reservation_datetime', $timeslot->start_at->format('Y-m-d H:i:s'));

        if ($timeslot->venue_id) {
            $reservationCountQuery->where('venue_id', $timeslot->venue_id);
        } else {
            $reservationCountQuery->whereNull('venue_id');
        }

        $reservationCount = $reservationCountQuery->count();
        if ($reservationCount >= $timeslot->capacity) {
            return redirect()
                ->route('admin.events.reservations.index', $event->id)
                ->with('error', 'この予約枠は満席です。');
        }

        // 顧客情報から予約を作成
        $reservation = EventReservation::create([
            'event_id' => $event->id,
            'document_id' => null,
            'name' => $customer->name,
            'email' => $validated['email'],
            'phone' => $customer->phone_number ?? '',
            'request_method' => null,
            'postal_code' => $customer->postal_code ?? null,
            'reservation_datetime' => $timeslot->start_at->format('Y-m-d H:i:s'),
            'venue_id' => $timeslot->venue_id,
            'has_visited_before' => false,
            'address' => $customer->address ?? null,
            'birth_date' => $customer->birth_date,
            'seijin_year' => $customer->coming_of_age_year ?? null,
            'referred_by_name' => $customer->referred_by_name ?? null,
            'furigana' => $customer->kana ?? null,
            'school_name' => $customer->school_name ?? null,
            'staff_name' => $customer->staff_name ?? null,
            'visit_reasons' => $customer->visit_reasons ?? null,
            'parking_usage' => null,
            'parking_car_count' => null,
            'considering_plans' => $customer->considering_plans ?? null,
            'heard_from' => null,
            'inquiry_message' => null,
            'privacy_agreed' => false,
            'customer_id' => $customer->id,
            'koichi_furisode_used' => null,
            'graduation_ceremony_year' => null,
            'graduation_ceremony_month' => null,
            'graduation_ceremony_date' => null,
            'visitor_count' => null,
        ]);

        app(EventReservationScheduleBootstrapService::class)->bootstrapIfApplicable($reservation);

        return redirect()
            ->route('admin.events.reservations.index', $event->id)
            ->with('success', '予約を登録しました。');
    }

    /**
     * 予約詳細を表示
     */
    public function show(Request $request, EventReservation $reservation)
    {
        $reservation->load(['event', 'venue', 'customer.ceremonyArea', 'notes.user', 'statusUpdatedBy', 'schedule.user', 'schedule.participantUsers', 'schedule.googleCalendarEventSyncs.shop', 'emailThreads.emails']);

        $indexFilters = array_filter([
            'venue_id' => $request->query('venue_id'),
            'reservation_datetime' => $request->query('reservation_datetime'),
        ]);

        $currentUser = auth()->user();
        $userShops = $currentUser ? $currentUser->shops()
            ->where('shops.is_active', true)
            ->select('shops.id', 'shops.name')
            ->orderBy('shops.name')
            ->get() : collect();

        // メールスレッドとメールを取得
        $emailThreads = $reservation->emailThreads()
            ->with(['emails' => function ($query) {
                $query->orderBy('created_at', 'asc');
            }])
            ->orderBy('created_at', 'desc')
            ->get();

        // イベントの開催店舗情報を取得
        $event = $reservation->event;
        $eventShops = $event->shops()
            ->where('shops.is_active', true)
            ->select('shops.id', 'shops.name')
            ->orderBy('shops.name')
            ->get();

        // キャンセル解除可能か（枠に空きがあるか）
        $canRestore = true;
        if ($reservation->cancel_flg && $event->usesTimeslotReservation() && $reservation->reservation_datetime) {
            $datetimeStr = Carbon::parse($reservation->reservation_datetime)->format('Y-m-d H:i:s');
            $timeslot = $event->timeslots()
                ->where('start_at', $datetimeStr)
                ->where('is_active', true);
            if ($reservation->venue_id) {
                $timeslot->where('venue_id', $reservation->venue_id);
            } else {
                $timeslot->whereNull('venue_id');
            }
            $timeslot = $timeslot->first();
            if ($timeslot) {
                // この予約を除いたキャンセルされていない予約数
                $reservedCount = $event->reservations()
                    ->where('id', '!=', $reservation->id)
                    ->where('cancel_flg', false)
                    ->where('reservation_datetime', $timeslot->start_at->format('Y-m-d H:i:s'));
                if ($timeslot->venue_id) {
                    $reservedCount->where('venue_id', $timeslot->venue_id);
                } else {
                    $reservedCount->whereNull('venue_id');
                }
                $reservedCount = $reservedCount->count();
                $canRestore = $reservedCount < $timeslot->capacity;
            }
        }

        $reservationId = $reservation->id;
        $noteIds = $reservation->notes()->pluck('id')->all();
        $activityLogsQuery = ActivityLog::query()
            ->with('user')
            ->where(function ($q) use ($reservationId, $noteIds) {
                $q->where(function ($q2) use ($reservationId) {
                    $q2->where('resource_type', 'EventReservation')
                        ->where('resource_id', $reservationId);
                });
                if ($noteIds !== []) {
                    $q->orWhere(function ($q2) use ($noteIds) {
                        $q2->where('resource_type', 'ReservationNote')
                            ->whereIn('resource_id', $noteIds);
                    });
                }
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(200);
        $activityLogs = $activityLogsQuery->get()->map(function (ActivityLog $log) {
            return [
                'id' => $log->id,
                'created_at' => $log->created_at->format('Y-m-d H:i:s'),
                'description' => $log->description,
                'operator_name' => $log->user?->name ?? '—',
            ];
        });

        $eventLineShopResolver = app(EventLineShopResolver::class);
        $canIssueLineLink = $eventLineShopResolver->resolveShopIdForEvent($event) !== null;

        $userShopsMapped = $userShops->map(function ($shop) {
            return [
                'id' => $shop->id,
                'name' => $shop->name,
            ];
        })->values()->all();

        $mapLineContact = static function ($c) {
            $uid = $c->line_user_id;

            return [
                'id' => $c->id,
                'label' => $c->label,
                'line_user_id_masked' => strlen($uid) > 8 ? substr($uid, 0, 4).'…'.substr($uid, -4) : '****',
                'shop_id' => $c->shop_id,
            ];
        };

        if ($reservation->customer_id) {
            $reservation->loadMissing(['customer.lineContacts']);
            $cust = $reservation->customer;
            $lineSection = [
                'context' => 'customer',
                'customer_id' => $cust->id,
                'reservation_id' => $reservation->id,
                'shops' => $userShopsMapped,
                'can_issue_line_link' => true,
                'line_contacts' => $cust ? $cust->lineContacts->map($mapLineContact)->values()->all() : [],
            ];
        } else {
            $reservation->loadMissing(['lineContacts']);
            $lineSection = [
                'context' => 'reservation',
                'customer_id' => null,
                'reservation_id' => $reservation->id,
                'shops' => [],
                'can_issue_line_link' => $canIssueLineLink,
                'line_contacts' => $reservation->lineContacts->map($mapLineContact)->values()->all(),
            ];
        }

        return Inertia::render('Admin/Reservation/Show', [
            'reservation' => $reservation,
            'event' => $reservation->event,
            'indexFilters' => $indexFilters,
            'venues' => $reservation->event->venues()->where('is_active', true)->get(),
            'notes' => $reservation->notes()->with('user')->orderBy('created_at', 'desc')->get(),
            'activity_logs' => $activityLogs,
            'schedule' => $reservation->schedule ? [
                'id' => $reservation->schedule->id,
                'title' => $reservation->schedule->title,
                'description' => $reservation->schedule->description,
                'start_at' => $reservation->schedule->start_at,
                'end_at' => $reservation->schedule->end_at,
                'all_day' => $reservation->schedule->all_day,
                'is_public' => $reservation->schedule->is_public ?? true,
                'sync_to_google_calendar' => (bool) $reservation->schedule->sync_to_google_calendar,
                'user' => $reservation->schedule->user ? [
                    'id' => $reservation->schedule->user->id,
                    'name' => $reservation->schedule->user->name,
                ] : null,
                'participantUsers' => $reservation->schedule->participantUsers->map(fn ($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                ])->toArray(),
            ] : null,
            'emailThreads' => $emailThreads,
            'canRestore' => $canRestore,
            'currentUser' => $currentUser ? [
                'id' => $currentUser->id,
                'name' => $currentUser->name,
            ] : null,
            'userShops' => $userShops->map(function ($shop) {
                return [
                    'id' => $shop->id,
                    'name' => $shop->name,
                ];
            }),
            'eventShops' => $eventShops->map(function ($shop) {
                return [
                    'id' => $shop->id,
                    'name' => $shop->name,
                ];
            }),
            'assigneeDatalistOptions' => $this->assigneeDatalistOptionsForEvent($event),
            'line_section' => $lineSection,
            'googleCalendarSyncInfo' => $this->buildGoogleCalendarSyncInfo($reservation, $eventShops),
        ]);
    }

    /**
     * 予約詳細画面で使用する Googleカレンダー連携情報を組み立てる
     */
    protected function buildGoogleCalendarSyncInfo(EventReservation $reservation, $eventShops): array
    {
        $schedule = $reservation->schedule;

        $syncs = [];
        if ($schedule) {
            $syncs = $schedule->googleCalendarEventSyncs
                ->map(function ($s) {
                    // Google Calendar の eid は "{eventId} {calendarId}" を base64url エンコードしたもの
                    $raw = $s->google_event_id . ' ' . $s->google_calendar_id;
                    $eid = rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');

                    return [
                        'id' => $s->id,
                        'shop_id' => $s->shop_id,
                        'shop_name' => $s->shop?->name,
                        'google_calendar_id' => $s->google_calendar_id,
                        'google_event_id' => $s->google_event_id,
                        'html_link' => 'https://www.google.com/calendar/event?eid=' . $eid,
                    ];
                })
                ->values()
                ->all();
        }

        $expectedShops = $eventShops->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
        ])->values()->all();

        $canSync = true;
        $cannotSyncReason = null;
        if ($reservation->cancel_flg) {
            $canSync = false;
            $cannotSyncReason = 'キャンセル済み予約はGoogleカレンダーに連携できません。';
        } elseif (empty($reservation->reservation_datetime)) {
            $canSync = false;
            $cannotSyncReason = '予約日時が未設定のためGoogleカレンダーに連携できません。';
        }

        return [
            'has_schedule' => $schedule !== null,
            'sync_enabled' => (bool) ($schedule?->sync_to_google_calendar),
            'syncs' => $syncs,
            'expected_shops' => $expectedShops,
            'can_sync' => $canSync,
            'cannot_sync_reason' => $cannotSyncReason,
        ];
    }

    /**
     * 予約編集フォームを表示
     */
    public function edit(EventReservation $reservation)
    {
        $reservation->load(['event', 'venue']);
        $event = $reservation->event;

        // 予約フォームの場合、利用可能な予約枠を取得（本日以降のみ・公開ページと同様）
        $timeslots = [];
        $venues = $event->venues()->where('venues.is_active', true)->get();
        if ($event->usesTimeslotReservation()) {
            $timeslots = $event->timeslots()
                ->where('is_active', true)
                ->whereDate('start_at', '>=', Carbon::today())
                ->orderBy('start_at', 'asc')
                ->get()
                ->map(function ($timeslot) use ($event, $reservation) {
                    $reservationCount = $event->reservations()
                        ->where('cancel_flg', false)
                        ->where('reservation_datetime', $timeslot->start_at->format('Y-m-d H:i:s'))
                        ->where('id', '!=', $reservation->id) // 現在編集中の予約を除外
                        ->count();
                    $timeslot->remaining_capacity = max(0, $timeslot->capacity - $reservationCount);

                    return $timeslot;
                });
            // 会場を予約枠の最終日が直近の順でソート（公開ページと同様）
            $lastSlotDatesByVenue = $event->timeslots()
                ->where('is_active', true)
                ->get()
                ->groupBy('venue_id')
                ->map(fn ($slots) => $slots->max('start_at'));
            $venues = $venues->sortBy(function ($venue) use ($lastSlotDatesByVenue) {
                $last = $lastSlotDatesByVenue->get($venue->id);

                return $last ?? Carbon::createFromDate(9999, 12, 31);
            })->values();
        }

        return Inertia::render('Admin/Reservation/Edit', [
            'reservation' => $reservation,
            'event' => $event,
            'venues' => $venues->map(fn ($v) => ['id' => $v->id, 'name' => $v->name]),
            'timeslots' => $timeslots,
        ]);
    }

    /**
     * 予約を更新
     */
    public function update(Request $request, EventReservation $reservation)
    {
        $reservation->load('event');
        $event = $reservation->event;

        // フォーム種別に応じたバリデーション
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'required|string|max:255',
        ];

        // 資料請求フォームの場合
        if ($event->form_type === 'document') {
            $rules['request_method'] = 'required|in:郵送,デジタルカタログ';
            $rules['postal_code'] = 'nullable|string|max:10';
            $rules['privacy_agreed'] = 'nullable|boolean';
        }

        // 振袖・袴（タイムスロット型）予約フォーム
        if ($event->usesTimeslotReservation()) {
            $rules['postal_code'] = $event->form_type === 'reservation_hakama'
                ? 'nullable|string|max:10'
                : 'nullable|string|max:10';
            $rules['reservation_datetime'] = 'nullable|string';
            $rules['venue_id'] = 'nullable|exists:venues,id';
            $rules['visit_reasons'] = 'nullable|array';
            $rules['visit_reasons.*'] = 'string|max:255';
            $rules['visit_reason_other'] = 'nullable|string|max:255';
            $rules['parking_usage'] = $event->form_type === 'reservation_hakama'
                ? 'required|in:なし,あり'
                : 'nullable|string|max:255';
            $rules['parking_car_count'] = 'nullable|integer';
            $rules['considering_plans'] = 'nullable|array';
            $rules['considering_plans.*'] = Rule::in($event->consideringPlanOptions());
        }

        if ($event->form_type === 'reservation') {
            $rules['has_visited_before'] = 'boolean';
            $rules['seijin_year'] = 'nullable|integer|min:2000|max:2100';
            $rules['referred_by_name'] = 'nullable|string|max:255';
            $rules['school_name'] = 'nullable|string|max:255';
            $rules['staff_name'] = 'nullable|string|max:255';
        }

        if ($event->form_type === 'reservation_hakama') {
            $rules['furigana'] = 'required|string|max:255';
            $rules['address'] = 'required|string|max:255';
            $rules['koichi_furisode_used'] = 'required|boolean';
            $rules['school_name'] = 'required|string|max:255';
            $rules['graduation_ceremony_date'] = 'required|date';
            $rules['visitor_count'] = 'required|integer|min:1|max:500';
            $rules['parking_car_count'] = 'nullable|integer|min:1|required_if:parking_usage,あり';
            $rules['referred_by_name'] = 'nullable|string|max:255';
        }

        // 共通項目
        if ($event->form_type !== 'reservation_hakama') {
            $rules['furigana'] = 'nullable|string|max:255';
            $rules['birth_date'] = 'nullable|date';
            $rules['address'] = 'nullable|string|max:255';
        } else {
            $rules['birth_date'] = 'nullable|date';
        }
        $rules['inquiry_message'] = 'nullable|string';

        // heard_fromのバリデーション（フォーム種別によって異なる）
        if ($event->form_type === 'contact') {
            // お問い合わせフォームの場合、「メール」「電話」のみ許可
            $rules['heard_from'] = 'nullable|in:メール,電話';
        } else {
            // その他のフォームの場合
            $rules['heard_from'] = 'nullable|string|max:255';
        }

        $validated = $request->validate($rules);

        // 来店動機を処理（「その他」の場合はテキスト入力も含める）
        if ($event->usesTimeslotReservation() && isset($validated['visit_reasons'])) {
            $validated['visit_reasons'] = $this->processVisitReasons($validated['visit_reasons'], $request->visit_reason_other);
        }

        if ($event->form_type === 'reservation_hakama') {
            $validated['graduation_ceremony_year'] = null;
            $validated['graduation_ceremony_month'] = null;
        }

        $reservation->update($validated);

        return redirect()->route('admin.reservations.show', $reservation->id)
            ->with('success', '予約を更新しました。');
    }

    /**
     * 予約をキャンセル（論理削除）
     * スケジュール登録済みの場合は先に削除（Googleカレンダーからも解除）
     */
    public function destroy(Request $request, EventReservation $reservation)
    {
        $reservation->load('schedule');

        if ($reservation->schedule) {
            $reservation->schedule->delete();
        }

        $reservation->update([
            'cancel_flg' => true,
            'status' => 'キャンセル済み',
        ]);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => '予約をキャンセルしました。',
                'reservation' => [
                    'id' => $reservation->id,
                    'cancel_flg' => $reservation->cancel_flg,
                    'status' => $reservation->status,
                ],
            ]);
        }

        return redirect()->route('admin.events.reservations.index', $reservation->event_id)
            ->with('success', '予約をキャンセルしました。');
    }

    /**
     * キャンセル済み予約を完全削除（物理削除）
     * 予約一覧から呼ぶ場合は axios で JSON を期待するが、削除後は一覧を再取得するためリダイレクトのままとする
     */
    public function forceDestroy(EventReservation $reservation)
    {
        if (! $reservation->cancel_flg) {
            return redirect()->back()
                ->with('error', 'キャンセル済みの予約のみ削除できます。');
        }

        $eventId = $reservation->event_id;
        $reservation->delete();

        return redirect()->route('admin.events.reservations.index', $eventId)
            ->with('success', '予約を削除しました。');
    }

    /**
     * 予約のキャンセルを解除（枠に空きがある場合のみ）
     */
    public function restore(Request $request, EventReservation $reservation)
    {
        $reservation->load('event');
        $event = $reservation->event;

        // 予約フォームで予約日時がある場合、枠の空きをチェック
        if ($event->usesTimeslotReservation() && $reservation->reservation_datetime) {
            $datetimeStr = Carbon::parse($reservation->reservation_datetime)->format('Y-m-d H:i:s');
            $timeslot = $event->timeslots()
                ->where('start_at', $datetimeStr)
                ->where('is_active', true);
            if ($reservation->venue_id) {
                $timeslot->where('venue_id', $reservation->venue_id);
            } else {
                $timeslot->whereNull('venue_id');
            }
            $timeslot = $timeslot->first();

            if ($timeslot) {
                $reservedCount = $event->reservations()
                    ->where('cancel_flg', false)
                    ->where('reservation_datetime', $timeslot->start_at->format('Y-m-d H:i:s'));
                if ($timeslot->venue_id) {
                    $reservedCount->where('venue_id', $timeslot->venue_id);
                } else {
                    $reservedCount->whereNull('venue_id');
                }
                $reservedCount = $reservedCount->count();

                if ($reservedCount >= $timeslot->capacity) {
                    if ($request->wantsJson() || $request->ajax()) {
                        return response()->json([
                            'success' => false,
                            'message' => '枠がいっぱいです。先に枠を増やしてください。',
                        ], 422);
                    }

                    return redirect()->back()
                        ->with('error', '枠がいっぱいです。先に枠を増やしてください。');
                }
            }
        }

        $reservation->update([
            'cancel_flg' => false,
            'status' => '未対応',
        ]);

        $reservation->refresh();
        $reservation->load(['event', 'venue']);
        app(EventReservationScheduleBootstrapService::class)->bootstrapIfApplicable($reservation);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'キャンセルを解除しました。',
                'reservation' => [
                    'id' => $reservation->id,
                    'cancel_flg' => $reservation->cancel_flg,
                    'status' => $reservation->status,
                ],
            ]);
        }

        return redirect()->back()
            ->with('success', 'キャンセルを解除しました。');
    }

    /**
     * メモを追加
     */
    public function storeNote(Request $request, EventReservation $reservation)
    {
        $validated = $request->validate([
            'content' => 'required|string|max:10000',
        ]);

        $note = ReservationNote::create([
            'user_id' => auth()->id(),
            'event_reservation_id' => $reservation->id,
            'content' => $validated['content'],
        ]);

        $note->load('user');

        return redirect()->back()->with('success', 'メモを追加しました。');
    }

    /**
     * メモを削除
     */
    public function destroyNote(ReservationNote $note)
    {
        $note->delete();

        return redirect()->back()->with('success', 'メモを削除しました。');
    }

    /**
     * 予約ステータスを更新
     * キャンセル済みに変更する場合、スケジュールがあれば削除（Googleカレンダーからも解除）
     */
    public function updateStatus(Request $request, EventReservation $reservation)
    {
        $validated = $request->validate([
            'status' => 'required|in:未対応,確認中,返信待ち,対応完了済み,キャンセル済み',
        ]);

        $oldStatus = $reservation->status;

        if ($validated['status'] === 'キャンセル済み') {
            $reservation->load('schedule');
            if ($reservation->schedule) {
                $reservation->schedule->delete();
            }
            $reservation->update([
                'status' => $validated['status'],
                'status_updated_by_user_id' => auth()->id(),
                'cancel_flg' => true,
            ]);
        } else {
            $reservation->update([
                'status' => $validated['status'],
                'status_updated_by_user_id' => auth()->id(),
            ]);
        }

        if ($validated['status'] !== 'キャンセル済み') {
            $reservation->load('schedule');
            if ($reservation->schedule && $reservation->schedule->sync_to_google_calendar) {
                app(GoogleCalendarSyncService::class)->syncScheduleToShopCalendarsOnUpdate(
                    $reservation->schedule->fresh(['participantUsers.shops'])
                );
            }
        }

        // ステータス変更をactivity_logsに記録
        ActivityLog::create([
            'user_id' => auth()->id(),
            'shop_id' => null,
            'action_type' => 'update',
            'resource_type' => 'EventReservation',
            'resource_id' => $reservation->id,
            'route_name' => $request->route() ? $request->route()->getName() : null,
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'description' => '予約ID:'.$reservation->id.' ステータスを '.($oldStatus ?? '-').'→'.$validated['status'].' に変更',
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => $validated['status']],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->back()->with('success', 'ステータスを'.$validated['status'].'に更新しました。');
    }

    /**
     * 管理画面の担当者（表示名）を更新
     */
    public function updateAssignee(Request $request, EventReservation $reservation)
    {
        $validated = $request->validate([
            'admin_assignee' => 'nullable|string|max:255',
        ]);

        $raw = $validated['admin_assignee'] ?? null;
        $value = $raw === null ? null : trim($raw);
        $reservation->update([
            'admin_assignee' => $value === '' ? null : $value,
        ]);

        return redirect()->back()->with('success', '担当者を更新しました。');
    }

    /**
     * 入場チケット送付ステータスを更新
     */
    public function updateEntranceTicketSendStatus(Request $request, EventReservation $reservation)
    {
        $validated = $request->validate([
            'entrance_ticket_send_status' => 'required|in:未送付,送付済み',
        ]);

        $reservation->update([
            'entrance_ticket_send_status' => $validated['entrance_ticket_send_status'],
        ]);

        return redirect()->back()->with('success', '入場チケット送付を更新しました。');
    }

    /**
     * 予約をスケジュールに追加
     */
    public function addToSchedule(Request $request, EventReservation $reservation)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'user_id' => 'required|exists:users,id',
            'start_at' => 'required|date',
            'end_at' => 'required|date|after_or_equal:start_at',
            'all_day' => 'boolean',
            'participant_ids' => 'nullable|array',
            'participant_ids.*' => 'exists:users,id',
        ]);

        $participantIds = ! empty($validated['participant_ids']) ? $validated['participant_ids'] : [];

        $reservation->load('schedule');

        if ($reservation->schedule) {
            $schedule = $reservation->schedule;
            $schedule->participantUsers()->sync($participantIds);
            $schedule->update([
                'user_id' => $validated['user_id'],
                'title' => $validated['title'],
                'description' => $validated['description'] ?? '',
                'start_at' => $validated['start_at'],
                'end_at' => $validated['end_at'],
                'all_day' => $validated['all_day'] ?? false,
                'sync_to_google_calendar' => true,
            ]);

            return redirect()->back()->with('success', 'スケジュールを更新しました。');
        }

        // スケジュールを作成（予約からの登録はGoogleカレンダー同期を有効で固定）
        $schedule = StaffSchedule::create([
            'user_id' => $validated['user_id'],
            'event_reservation_id' => $reservation->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? '',
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'],
            'all_day' => $validated['all_day'] ?? false,
            'sync_to_google_calendar' => true,
        ]);

        $schedule->participantUsers()->sync($participantIds);

        app(GoogleCalendarSyncService::class)->syncScheduleToShopCalendars($schedule);

        return redirect()->back()->with('success', 'スケジュールに追加しました。');
    }

    /**
     * 予約をスケジュールから解除
     */
    public function removeFromSchedule(EventReservation $reservation)
    {
        if (! $reservation->schedule) {
            return redirect()->back()->with('error', 'この予約はスケジュールに追加されていません。');
        }

        $reservation->schedule->delete();

        return redirect()->back()->with('success', 'スケジュールから解除しました。');
    }

    /**
     * 予約をGoogleカレンダーに手動連携する
     * - スケジュールがなければ作成してGoogleカレンダーへ同期
     * - スケジュールがあれば再同期（差分反映）
     */
    public function syncGoogleCalendar(EventReservation $reservation)
    {
        if ($reservation->cancel_flg) {
            return redirect()->back()->with('error', 'キャンセル済み予約はGoogleカレンダーに連携できません。');
        }
        if (empty($reservation->reservation_datetime)) {
            return redirect()->back()->with('error', '予約日時が未設定のためGoogleカレンダーに連携できません。');
        }

        $reservation->load(['event', 'venue', 'schedule']);

        try {
            if ($reservation->schedule === null) {
                // スケジュールがない → BootstrapService で作成＆同期
                // （管理画面ログイン中なので Auth::id() が必ず取れる）
                app(EventReservationScheduleBootstrapService::class)
                    ->bootstrapIfApplicable($reservation);
            } else {
                // 既存スケジュールあり → 再同期
                $schedule = $reservation->schedule;
                if (! $schedule->sync_to_google_calendar) {
                    $schedule->update(['sync_to_google_calendar' => true]);
                    $schedule->refresh();
                }
                app(GoogleCalendarSyncService::class)
                    ->syncScheduleToShopCalendarsOnUpdate($schedule);
            }
        } catch (\Throwable $e) {
            Log::error('[GoogleCalendar] 手動連携失敗', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()->back()->with('error', 'Googleカレンダー連携に失敗しました: ' . $e->getMessage());
        }

        return redirect()->back()->with('success', 'Googleカレンダーに連携しました。');
    }

    /**
     * 受信メールの添付ファイルをダウンロード
     */
    public function downloadEmailAttachment(EmailAttachment $attachment)
    {
        if (empty($attachment->path) || ! Storage::disk('local')->exists($attachment->path)) {
            abort(404, '添付ファイルが見つかりません。');
        }

        return Storage::disk('local')->download($attachment->path, $attachment->filename);
    }

    /**
     * 予約に顧客を紐づける
     */
    public function linkCustomer(Request $request, EventReservation $reservation)
    {
        $validated = $request->validate([
            'customer_id' => 'required|exists:customers,id',
        ]);

        $customer = Customer::findOrFail($validated['customer_id']);
        $reservation->update(['customer_id' => $customer->id]);

        $migrated = app(ReservationLineContactMigrator::class)->migrateReservationContactsToCustomer($reservation, $customer);
        if (! $migrated['ok']) {
            return redirect()->back()->with('error', $migrated['message'] ?? 'LINE の引き継ぎに失敗しました。');
        }

        return redirect()->back()->with('success', '顧客を紐づけました。');
    }

    /**
     * 予約から顧客紐づけを解除する
     */
    public function unlinkCustomer(EventReservation $reservation)
    {
        $reservation->update(['customer_id' => null]);

        return redirect()->back()->with('success', '顧客の紐づけを解除しました。');
    }

    /**
     * 返信メールを送信（新規メール作成にも対応）
     */
    public function sendReplyEmail(Request $request, EventReservation $reservation)
    {
        // バリデーションルールを条件分岐
        $rules = [
            'message' => 'required|string',
        ];

        // email_thread_idが存在する場合は既存スレッドへの返信
        if ($request->has('email_thread_id') && $request->email_thread_id !== null) {
            $rules['email_thread_id'] = 'required|exists:email_threads,id';
        } else {
            // 新規メール作成の場合は件名が必須
            $rules['subject'] = 'required|string|max:255';
        }

        $validated = $request->validate($rules);

        // 新規スレッド作成または既存スレッド取得
        if (! isset($validated['email_thread_id']) || $validated['email_thread_id'] === null) {
            // 新規スレッドを作成
            $emailThread = EmailThread::create([
                'event_reservation_id' => $reservation->id,
                'subject' => $validated['subject'],
            ]);

            $inReplyTo = null;
            $references = null;
        } else {
            // 既存スレッドを取得
            $emailThread = EmailThread::with('emails')->findOrFail($validated['email_thread_id']);

            // スレッドがこの予約に紐づいているか確認
            if ($emailThread->event_reservation_id !== $reservation->id) {
                return redirect()->back()->with('error', 'このスレッドはこの予約に紐づいていません。');
            }

            // スレッド内の最新の受信メール（fromが予約者のメールアドレス）を取得（In-Reply-To用）
            $latestReceivedEmail = $emailThread->emails()
                ->where('from', $reservation->email)
                ->orderBy('created_at', 'desc')
                ->first();

            // In-Reply-To：最新の受信メールのMessage-IDを使用（そのまま使用、修正しない）
            $inReplyTo = $latestReceivedEmail && $latestReceivedEmail->message_id
                ? $latestReceivedEmail->message_id
                : null;

            // スレッド内のすべてのメールのMessage-IDを取得（References用：古い順）
            // message_idはそのまま使用（修正しない）
            $allMessageIds = $emailThread->emails()
                ->orderBy('created_at', 'asc')
                ->pluck('message_id')
                ->filter()
                ->values();

            // References：スレッド内のすべてのMessage-IDを古い順にスペース区切りで結合
            $references = $allMessageIds->implode(' ');

            // デバッグ用ログ：返信メール送信準備
            Log::info('返信メール送信準備 - ヘッダー情報確認', [
                'email_thread_id' => $emailThread->id,
                'latest_received_email_id' => $latestReceivedEmail ? $latestReceivedEmail->id : null,
                'latest_received_email_from' => $latestReceivedEmail ? $latestReceivedEmail->from : null,
                'latest_received_email_message_id' => $latestReceivedEmail ? $latestReceivedEmail->message_id : null,
                'in_reply_to' => $inReplyTo,
                'references' => $references,
                'total_emails_in_thread' => $allMessageIds->count(),
                'all_message_ids' => $allMessageIds->toArray(),
            ]);
        }

        // メールを送信
        $mailable = new ReservationReplyMail($emailThread, $reservation->email, $validated['message'], $inReplyTo, $references);

        // In-Reply-ToとReferencesヘッダーを設定（返信の場合のみ）
        // envelope()とcontent()を使う場合、withSwiftMessageはコントローラー側で呼ぶ必要がある
        if ($inReplyTo || $references) {
            $mailable->withSwiftMessage(function ($swiftMessage) use ($inReplyTo, $references) {
                if ($inReplyTo) {
                    $swiftMessage->getHeaders()->addTextHeader('In-Reply-To', $inReplyTo);
                }
                if ($references) {
                    $swiftMessage->getHeaders()->addTextHeader('References', $references);
                }
            });
        }

        Mail::to($reservation->email)->send($mailable);

        // デバッグ用：実際に送信されたメールのヘッダーを確認
        Log::info('返信メール送信 - ヘッダー確認', [
            'email_thread_id' => $emailThread->id,
            'subject' => $mailable->envelope()->subject,
            'in_reply_to' => $inReplyTo,
            'references' => $references,
            'in_reply_to_format_check' => $inReplyTo ? (preg_match('/^<.*@.*>$/', $inReplyTo) ? 'RFC 5322形式（正常）' : '形式不正') : 'null',
        ]);

        // Message-IDを生成（RFC 5322形式）
        $messageId = '<reservation-reply-'.$reservation->id.'-'.now()->timestamp.'@'.parse_url(config('app.url'), PHP_URL_HOST).'>';

        // 送信したメールをデータベースに保存
        $rawEmail = $this->buildRawEmail($mailable, $reservation->email, $emailThread, $validated['message'], $messageId, $inReplyTo, $references);

        // デバッグ用：実際に送信されたメールの生データをログに記録
        Log::info('返信メール送信 - raw_email', [
            'reservation_id' => $reservation->id,
            'email_thread_id' => $emailThread->id,
            'raw_email' => $rawEmail,
        ]);

        Email::create([
            'message_id' => $messageId,
            'from' => config('mail.from.address'),
            'to' => $reservation->email,
            'subject' => $mailable->envelope()->subject,
            'text_body' => view('mail.reservation_reply_plain', [
                'emailThread' => $emailThread,
                'replyMessage' => $validated['message'],
            ])->render(),
            'html_body' => null,
            'raw_email' => $rawEmail,
            'event_reservation_id' => $reservation->id,
            'email_thread_id' => $emailThread->id,
        ]);

        $logMessage = isset($validated['email_thread_id']) ? '返信メールを送信しました' : '新規メールを送信しました';
        Log::info($logMessage, [
            'reservation_id' => $reservation->id,
            'email' => $reservation->email,
            'email_thread_id' => $emailThread->id,
        ]);

        $successMessage = isset($validated['email_thread_id']) ? '返信メールを送信しました。' : 'メールを送信しました。';

        return redirect()->back()->with('success', $successMessage);
    }

    /**
     * メールの生データを構築
     */
    private function buildRawEmail($mailable, $to, $emailThread, $messageText, $messageId, $inReplyTo = null, $references = null)
    {
        $envelope = $mailable->envelope();

        $subject = $envelope->subject;
        $from = config('mail.from.address');
        $fromName = config('mail.from.name');

        $textBody = view('mail.reservation_reply_plain', [
            'emailThread' => $emailThread,
            'replyMessage' => $messageText,
        ])->render();

        $rawEmail = "Message-ID: {$messageId}\r\n";
        $rawEmail .= "From: {$fromName} <{$from}>\r\n";
        $rawEmail .= "To: {$to}\r\n";
        $rawEmail .= "Subject: {$subject}\r\n";
        $rawEmail .= "Reply-To: reply@reply.kyogofuku-hirata.jp\r\n";

        // 返信メールのヘッダーを追加
        if ($inReplyTo) {
            $rawEmail .= "In-Reply-To: {$inReplyTo}\r\n";
        }
        if ($references) {
            $rawEmail .= "References: {$references}\r\n";
        }

        $rawEmail .= 'Date: '.now()->format('r')."\r\n";
        $rawEmail .= "MIME-Version: 1.0\r\n";
        $rawEmail .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $rawEmail .= "\r\n";
        $rawEmail .= $textBody;

        return $rawEmail;
    }

    /**
     * イベント開催店舗に所属するスタッフ名＋固定候補（担当者入力の datalist 用）
     *
     * @return list<string>
     */
    private function assigneeDatalistOptionsForEvent(Event $event): array
    {
        $shopIds = $event->shops()
            ->where('shops.is_active', true)
            ->pluck('shops.id')
            ->all();

        $names = [];
        if ($shopIds !== []) {
            $names = User::query()
                ->whereHas('shops', function ($q) use ($shopIds) {
                    $q->whereIn('shops.id', $shopIds);
                })
                ->orderBy('name')
                ->pluck('name')
                ->unique()
                ->values()
                ->all();
        }

        $fixed = ['岡F', '城東F', 'EF'];

        return array_values(array_unique(array_merge($names, $fixed)));
    }

    /**
     * 来店動機を処理（「その他」の場合はテキスト入力も含める）
     */
    private function processVisitReasons($visitReasons, $visitReasonOther)
    {
        if (! $visitReasons || ! is_array($visitReasons)) {
            return null;
        }

        $reasons = [];
        foreach ($visitReasons as $reason) {
            if ($reason === 'その他' && $visitReasonOther) {
                $reasons[] = 'その他('.$visitReasonOther.')';
            } else {
                $reasons[] = $reason;
            }
        }

        return ! empty($reasons) ? $reasons : null;
    }
}
