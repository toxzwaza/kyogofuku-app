<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ReservationReplyMail;
use App\Models\Email;
use App\Models\EmailThread;
use App\Models\Event;
use App\Models\EventReservation;
use App\Models\ReservationNote;
use App\Models\StaffSchedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use App\Models\ActivityLog;
use Carbon\Carbon;

class ReservationController extends Controller
{
    /**
     * イベント別予約一覧を表示
     */
    public function index(Request $request, Event $event)
    {
        // 会場リストを取得
        $venues = $event->venues()->where('is_active', true)->orderBy('name')->get();
        
        // 予約クエリを作成
        $reservationsQuery = EventReservation::with(['venue', 'statusUpdatedBy', 'schedule.participantUsers'])
            ->where('event_id', $event->id);
        
        // 会場で絞り込み
        if ($request->filled('venue_id')) {
            $reservationsQuery->where('venue_id', $request->venue_id);
        }
        
        // 時間で絞り込み（予約フォームの場合のみ）
        if ($event->form_type === 'reservation' && $request->filled('reservation_datetime')) {
            $datetime = $request->reservation_datetime;
            // 日付のみ（YYYY-MM-DD形式）の場合は、その日のすべての予約を取得
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $datetime)) {
                $reservationsQuery->whereDate('reservation_datetime', $datetime);
            } else {
                // 日時形式（YYYY-MM-DD HH:MM:SS形式）の場合は完全一致
                $reservationsQuery->where('reservation_datetime', $datetime);
            }
        }
        
        $reservations = $reservationsQuery->orderBy('created_at', 'desc')->get()->map(function ($reservation) {
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
                'cancel_flg' => $reservation->cancel_flg,
            ];
        });
        
        // 時間リストを取得（予約フォームの場合のみ、フィルター用）
        $filterTimeslots = [];
        if ($event->form_type === 'reservation') {
            $timeslotsQuery = $event->timeslots()->with('venue')->where('is_active', true);
            
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
        
        if ($event->form_type === 'reservation') {
            $timeslotsQuery = $event->timeslots()->with('venue')->where('is_active', true);
            
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
                    ->with(['venue', 'statusUpdatedBy', 'schedule.participantUsers'])
                    ->where('reservation_datetime', $timeslot->start_at->format('Y-m-d H:i:s'));
                
                // 予約枠に会場IDが設定されている場合、同じ会場の予約のみ取得
                if ($timeslot->venue_id) {
                    $timeslotReservationsQuery->where('venue_id', $timeslot->venue_id);
                } else {
                    // 予約枠に会場IDが設定されていない場合、venue_idがnullの予約のみ取得
                    $timeslotReservationsQuery->whereNull('venue_id');
                }
                
                $timeslotReservations = (clone $timeslotReservationsQuery)
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
            ],
        ]);
    }

    /**
     * 予約詳細を表示
     */
    public function show(EventReservation $reservation)
    {
        $reservation->load(['event', 'venue', 'notes.user', 'statusUpdatedBy', 'schedule', 'emailThreads.emails']);
        
        $currentUser = auth()->user();
        $userShops = $currentUser ? $currentUser->shops()
            ->where('shops.is_active', true)
            ->select('shops.id', 'shops.name')
            ->orderBy('shops.name')
            ->get() : collect();

        // メールスレッドとメールを取得
        $emailThreads = $reservation->emailThreads()
            ->with(['emails' => function($query) {
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
        if ($reservation->cancel_flg && $event->form_type === 'reservation' && $reservation->reservation_datetime) {
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

        return Inertia::render('Admin/Reservation/Show', [
            'reservation' => $reservation,
            'event' => $reservation->event,
            'venues' => $reservation->event->venues()->where('is_active', true)->get(),
            'notes' => $reservation->notes()->with('user')->orderBy('created_at', 'desc')->get(),
            'schedule' => $reservation->schedule,
            'emailThreads' => $emailThreads,
            'canRestore' => $canRestore,
            'currentUser' => $currentUser ? [
                'id' => $currentUser->id,
                'name' => $currentUser->name,
            ] : null,
            'userShops' => $userShops->map(function($shop) {
                return [
                    'id' => $shop->id,
                    'name' => $shop->name,
                ];
            }),
            'eventShops' => $eventShops->map(function($shop) {
                return [
                    'id' => $shop->id,
                    'name' => $shop->name,
                ];
            }),
        ]);
    }

    /**
     * 予約編集フォームを表示
     */
    public function edit(EventReservation $reservation)
    {
        $reservation->load(['event', 'venue']);
        $event = $reservation->event;

        // 予約フォームの場合、利用可能な予約枠を取得
        $timeslots = [];
        if ($event->form_type === 'reservation') {
            $timeslots = $event->timeslots()
                ->where('is_active', true)
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
        }

        return Inertia::render('Admin/Reservation/Edit', [
            'reservation' => $reservation,
            'event' => $event,
            'venues' => $event->venues()->where('is_active', true)->get(),
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
        
        // 予約フォームの場合
        if ($event->form_type === 'reservation') {
            $rules['postal_code'] = 'nullable|string|max:10';
            $rules['reservation_datetime'] = 'nullable|string';
            $rules['venue_id'] = 'nullable|exists:venues,id';
            $rules['has_visited_before'] = 'boolean';
            $rules['seijin_year'] = 'nullable|integer|min:2000|max:2100';
            $rules['referred_by_name'] = 'nullable|string|max:255';
            $rules['school_name'] = 'nullable|string|max:255';
            $rules['staff_name'] = 'nullable|string|max:255';
            $rules['visit_reasons'] = 'nullable|array';
            $rules['visit_reasons.*'] = 'string|max:255';
            $rules['visit_reason_other'] = 'nullable|string|max:255';
            $rules['parking_usage'] = 'nullable|string|max:255';
            $rules['parking_car_count'] = 'nullable|integer';
            $rules['considering_plans'] = 'nullable|array';
            $rules['considering_plans.*'] = 'in:振袖レンタルプラン,振袖購入プラン,ママ振りフォトプラン,フォトレンタルプラン';
        }
        
        // 共通項目
        $rules['furigana'] = 'nullable|string|max:255';
        $rules['birth_date'] = 'nullable|date';
        $rules['address'] = 'nullable|string|max:255';
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
        if ($event->form_type === 'reservation' && isset($validated['visit_reasons'])) {
            $validated['visit_reasons'] = $this->processVisitReasons($validated['visit_reasons'], $request->visit_reason_other);
        }

        $reservation->update($validated);

        return redirect()->route('admin.reservations.show', $reservation->id)
            ->with('success', '予約を更新しました。');
    }

    /**
     * 予約をキャンセル（論理削除）
     */
    public function destroy(Request $request, EventReservation $reservation)
    {
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
        if (!$reservation->cancel_flg) {
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
        if ($event->form_type === 'reservation' && $reservation->reservation_datetime) {
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
     */
    public function updateStatus(Request $request, EventReservation $reservation)
    {
        $validated = $request->validate([
            'status' => 'required|in:未対応,確認中,返信待ち,対応完了済み,キャンセル済み',
        ]);

        $oldStatus = $reservation->status;
        $reservation->update([
            'status' => $validated['status'],
            'status_updated_by_user_id' => auth()->id(),
        ]);

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
            'description' => '予約ID:' . $reservation->id . ' ステータスを ' . ($oldStatus ?? '-') . '→' . $validated['status'] . ' に変更',
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => $validated['status']],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->back()->with('success', 'ステータスを'.$validated['status'].'に更新しました。');
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

        // 既にスケジュールが存在する場合はエラー
        if ($reservation->schedule) {
            return redirect()->back()->with('error', 'この予約は既にスケジュールに追加されています。');
        }

        // スケジュールを作成
        $schedule = StaffSchedule::create([
            'user_id' => $validated['user_id'],
            'event_reservation_id' => $reservation->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? '',
            'start_at' => $validated['start_at'],
            'end_at' => $validated['end_at'],
            'all_day' => $validated['all_day'] ?? false,
            'color' => '#3788d8',
        ]);

        // 参加者を設定（user_idは自動で含めない - チェックボックスで選択された場合のみ含める）
        $participantIds = [];
        if (!empty($validated['participant_ids'])) {
            $participantIds = $validated['participant_ids'];
        }
        $schedule->participantUsers()->sync($participantIds);

        return redirect()->back()->with('success', 'スケジュールに追加しました。');
    }

    /**
     * 予約をスケジュールから解除
     */
    public function removeFromSchedule(EventReservation $reservation)
    {
        if (!$reservation->schedule) {
            return redirect()->back()->with('error', 'この予約はスケジュールに追加されていません。');
        }

        $reservation->schedule->delete();

        return redirect()->back()->with('success', 'スケジュールから解除しました。');
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
        if (!isset($validated['email_thread_id']) || $validated['email_thread_id'] === null) {
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
        $messageId = '<reservation-reply-' . $reservation->id . '-' . now()->timestamp . '@' . parse_url(config('app.url'), PHP_URL_HOST) . '>';

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
        
        $rawEmail .= "Date: " . now()->format('r') . "\r\n";
        $rawEmail .= "MIME-Version: 1.0\r\n";
        $rawEmail .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $rawEmail .= "\r\n";
        $rawEmail .= $textBody;
        
        return $rawEmail;
    }

    /**
     * 来店動機を処理（「その他」の場合はテキスト入力も含める）
     */
    private function processVisitReasons($visitReasons, $visitReasonOther)
    {
        if (!$visitReasons || !is_array($visitReasons)) {
            return null;
        }

        $reasons = [];
        foreach ($visitReasons as $reason) {
            if ($reason === 'その他' && $visitReasonOther) {
                $reasons[] = 'その他(' . $visitReasonOther . ')';
            } else {
                $reasons[] = $reason;
            }
        }

        return !empty($reasons) ? $reasons : null;
    }
}

