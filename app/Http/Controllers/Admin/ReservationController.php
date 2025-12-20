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
    public function index(Event $event)
    {
        $reservations = EventReservation::with(['venue', 'statusUpdatedBy'])
            ->where('event_id', $event->id)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // 予約枠の統計情報と日付別グループ化（予約フォームの場合のみ）
        $timeslotStats = null;
        $timeslotsWithReservations = null;
        
        if ($event->form_type === 'reservation') {
            $timeslots = $event->timeslots()->where('is_active', true)->orderBy('start_at', 'asc')->get();
            $totalCapacity = $timeslots->sum('capacity');
            $totalReserved = 0;
            
            // 各予約枠に予約情報を紐付け
            $timeslotsWithReservations = $timeslots->map(function ($timeslot) use ($event, &$totalReserved) {
                $timeslotReservations = $event->reservations()
                    ->with(['venue', 'statusUpdatedBy'])
                    ->where('reservation_datetime', $timeslot->start_at->format('Y-m-d H:i:s'))
                    ->orderBy('created_at', 'asc')
                    ->get();
                
                $reservedCount = $timeslotReservations->count();
                $totalReserved += $reservedCount;
                
                return [
                    'id' => $timeslot->id,
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

        return Inertia::render('Admin/Reservation/Show', [
            'reservation' => $reservation,
            'event' => $reservation->event,
            'venues' => $reservation->event->venues()->where('is_active', true)->get(),
            'notes' => $reservation->notes()->with('user')->orderBy('created_at', 'desc')->get(),
            'schedule' => $reservation->schedule,
            'emailThreads' => $emailThreads,
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
     * 予約を削除
     */
    public function destroy(EventReservation $reservation)
    {
        $eventId = $reservation->event_id;
        $reservation->delete();

        return redirect()->route('admin.events.reservations.index', $eventId)
            ->with('success', '予約を削除しました。');
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
            'status' => 'required|in:未対応,確認中,返信待ち,対応完了済み',
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

        // 作成者を参加者として追加
        $participantIds = [$validated['user_id']];
        if (!empty($validated['participant_ids'])) {
            $participantIds = array_unique(array_merge($participantIds, $validated['participant_ids']));
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
     * 返信メールを送信
     */
    public function sendReplyEmail(Request $request, EventReservation $reservation)
    {
        $validated = $request->validate([
            'email_thread_id' => 'required|exists:email_threads,id',
            'message' => 'required|string',
        ]);

        // スレッドを取得
        $emailThread = EmailThread::with('emails')->findOrFail($validated['email_thread_id']);
        
        // スレッドがこの予約に紐づいているか確認
        if ($emailThread->event_reservation_id !== $reservation->id) {
            return redirect()->back()->with('error', 'このスレッドはこの予約に紐づいていません。');
        }

        // スレッド内の最新のメールを取得（返信先として使用）
        $latestEmail = $emailThread->emails()->orderBy('created_at', 'desc')->first();
        $inReplyTo = $latestEmail ? $latestEmail->message_id : null;
        
        // スレッド内のすべてのメールのMessage-IDを取得（References用）
        $references = $emailThread->emails()
            ->orderBy('created_at', 'asc')
            ->pluck('message_id')
            ->filter()
            ->implode(' ');

        // メールを送信
        $mailable = new ReservationReplyMail($emailThread, $reservation->email, $validated['message'], $inReplyTo, $references);
        
        // カスタムヘッダーを追加
        $mailable->withSwiftMessage(function ($swiftMessage) use ($inReplyTo, $references) {
            if ($inReplyTo) {
                $swiftMessage->getHeaders()->addTextHeader('In-Reply-To', $inReplyTo);
            }
            if ($references) {
                $swiftMessage->getHeaders()->addTextHeader('References', $references);
            }
        });
        
        Mail::to($reservation->email)->send($mailable);

        // Message-IDを生成（RFC 5322形式）
        $messageId = '<reservation-reply-' . $reservation->id . '-' . now()->timestamp . '@' . parse_url(config('app.url'), PHP_URL_HOST) . '>';

        // 送信したメールをデータベースに保存
        $rawEmail = $this->buildRawEmail($mailable, $reservation->email, $emailThread, $validated['message'], $messageId, $inReplyTo, $references);
        
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

        Log::info('返信メールを送信しました', [
            'reservation_id' => $reservation->id,
            'email' => $reservation->email,
            'email_thread_id' => $emailThread->id,
        ]);

        return redirect()->back()->with('success', '返信メールを送信しました。');
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

