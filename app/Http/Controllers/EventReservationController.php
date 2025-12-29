<?php

namespace App\Http\Controllers;

use App\Mail\ReservationConfirmationMail;
use App\Models\Email;
use App\Models\EmailThread;
use App\Models\Event;
use App\Models\EventReservation;
use App\Models\EventTimeslot;
use App\Http\Controllers\LineWebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class EventReservationController extends Controller
{
    /**
     * 予約を保存
     */
    public function store(Request $request, Event $event)
    {
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
            $rules['privacy_agreed'] = 'required|boolean|accepted';
            $rules['document_id'] = 'required|exists:documents,id';
        }
        
        // 予約フォームの場合
        if ($event->form_type === 'reservation') {
            $rules['reservation_datetime'] = 'nullable|string';
            $rules['venue_id'] = 'nullable|exists:venues,id';
            $rules['timeslot_id'] = 'nullable|exists:event_timeslots,id';
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
        
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        // 予約フォームの場合、予約枠の検証
        $timeslot = null;
        $venueId = null;
        $reservationDatetime = null;
        
        if ($event->form_type === 'reservation') {
            if ($request->timeslot_id) {
                // 予約枠IDが指定されている場合、そのIDで直接取得
                $timeslot = EventTimeslot::where('event_id', $event->id)
                    ->where('id', $request->timeslot_id)
                    ->where('is_active', true)
                    ->first();

                if (!$timeslot) {
                    throw ValidationException::withMessages([
                        'timeslot_id' => ['選択された予約枠が見つかりません。'],
                    ]);
                }

                // 予約枠から会場IDと予約日時を取得
                $venueId = $timeslot->venue_id ?? $request->venue_id;
                $reservationDatetime = $timeslot->start_at->format('Y-m-d H:i:s');
                
                // 残枠チェック（予約枠IDに基づいて、同じ会場・同じ時間の予約のみカウント）
                $reservationCountQuery = EventReservation::where('event_id', $event->id)
                    ->where('reservation_datetime', $reservationDatetime);
                
                // 予約枠に会場IDが設定されている場合、同じ会場の予約のみカウント
                if ($timeslot->venue_id) {
                    $reservationCountQuery->where('venue_id', $timeslot->venue_id);
                } else {
                    // 予約枠に会場IDが設定されていない場合、venue_idがnullの予約のみカウント
                    $reservationCountQuery->whereNull('venue_id');
                }
                
                $reservationCount = $reservationCountQuery->count();

                if ($reservationCount >= $timeslot->capacity) {
                    throw ValidationException::withMessages([
                        'timeslot_id' => ['この予約枠は満席です。'],
                    ]);
                }
            } elseif ($request->reservation_datetime) {
                // 予約枠IDが指定されていない場合のフォールバック（既存の処理）
                $reservationDatetime = \Carbon\Carbon::parse($request->reservation_datetime);
                
                $timeslot = EventTimeslot::where('event_id', $event->id)
                    ->where('start_at', $reservationDatetime->format('Y-m-d H:i:s'))
                    ->where('is_active', true)
                    ->first();

                if (!$timeslot) {
                    throw ValidationException::withMessages([
                        'reservation_datetime' => ['選択された予約枠が見つかりません。'],
                    ]);
                }

                // 残枠チェック
                $reservationCount = EventReservation::where('event_id', $event->id)
                    ->where('reservation_datetime', $reservationDatetime->format('Y-m-d H:i:s'))
                    ->count();

                if ($reservationCount >= $timeslot->capacity) {
                    throw ValidationException::withMessages([
                        'reservation_datetime' => ['この予約枠は満席です。'],
                    ]);
                }
                
                $venueId = $request->venue_id;
                $reservationDatetime = $reservationDatetime->format('Y-m-d H:i:s');
            } else {
                $venueId = $request->venue_id;
                $reservationDatetime = $request->reservation_datetime;
            }
        } else {
            $venueId = $request->venue_id;
            $reservationDatetime = $request->reservation_datetime;
        }

        $reservation = EventReservation::create([
            'event_id' => $event->id,
            'document_id' => $request->document_id,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'request_method' => $request->request_method,
            'postal_code' => $request->postal_code,
            'reservation_datetime' => $reservationDatetime,
            'venue_id' => $venueId,
            'has_visited_before' => $request->has('has_visited_before') ? $request->has_visited_before : false,
            'address' => $request->address,
            'birth_date' => $request->birth_date,
            'seijin_year' => $request->seijin_year,
            'referred_by_name' => $request->referred_by_name,
            'furigana' => $request->furigana,
            'school_name' => $request->school_name,
            'staff_name' => $request->staff_name,
            'visit_reasons' => $this->processVisitReasons($request->visit_reasons, $request->visit_reason_other),
            'parking_usage' => $request->parking_usage,
            'parking_car_count' => $request->parking_car_count,
            'considering_plans' => $request->considering_plans,
            'heard_from' => $request->heard_from,
            'inquiry_message' => $request->inquiry_message,
            'privacy_agreed' => $request->has('privacy_agreed') ? $request->privacy_agreed : false,
        ]);

        // リレーションをロード（LINE通知で使用）
        $reservation->load('venue');

        // 送信データをセッションに保存（成功ページで表示するため）
        $formData = $request->only([
            'name', 'email', 'phone', 'request_method', 'postal_code',
            'reservation_datetime', 'venue_id', 'has_visited_before',
            'address', 'birth_date', 'seijin_year', 'referred_by_name',
            'furigana', 'school_name', 'staff_name', 'visit_reasons', 'visit_reason_other',
            'parking_usage', 'parking_car_count',
            'considering_plans', 'heard_from', 'inquiry_message', 'privacy_agreed'
        ]);
        // visit_reasonsを処理済みの値に置き換え
        $formData['visit_reasons'] = $this->processVisitReasons($request->visit_reasons, $request->visit_reason_other);

        // セッションにデータを保存
        $request->session()->put('formData', $formData);

        // LINE通知を送信（エラーが発生しても予約処理は続行）
        try {
            $this->sendLineNotification($event, $reservation);
        } catch (\Exception $e) {
            Log::error('LINE通知の送信に失敗しました: ' . $e->getMessage(), [
                'reservation_id' => $reservation->id,
                'event_id' => $event->id,
            ]);
        }

        // 管理画面からの登録の場合はメール送信をスキップ
        $fromAdmin = $request->has('from_admin') && $request->from_admin;
        if (!$fromAdmin) {
            // 受付完了メールを送信（エラーが発生しても予約処理は続行）
            try {
                $this->sendReservationConfirmationEmail($reservation);
            } catch (\Exception $e) {
                Log::error('受付完了メールの送信に失敗しました: ' . $e->getMessage(), [
                    'reservation_id' => $reservation->id,
                    'event_id' => $event->id,
                ]);
            }
        }

        // 管理画面からの登録の場合は予約一覧画面へリダイレクト
        if ($fromAdmin) {
            return redirect()
                ->route('admin.events.reservations.index', $event->id)
                ->with('success', '予約を登録しました。');
        }

        // 通常の場合は成功ページへリダイレクト（Inertiaリクエストの場合も正しく動作する）
        return redirect()->route('event.reserve.success', $event->id);
    }

    /**
     * 送信完了ページを表示
     */
    public function success(Request $request, Event $event)
    {
        // セッションから送信データを取得
        $formData = $request->session()->get('formData');

        // セッションにデータがない場合は、イベントページにリダイレクト
        if (!$formData) {
            return redirect()->route('event.show', $event->slug);
        }

        // イベント情報を取得
        $event = Event::with(['images', 'venues'])
            ->where('id', $event->id)
            ->where('is_public', true)
            ->firstOrFail();

        // 会場情報（予約フォームの場合のみ）
        $venues = [];
        if ($event->form_type === 'reservation') {
            $venues = $event->venues->where('is_active', true)->map(function ($venue) {
                return [
                    'id' => $venue->id,
                    'name' => $venue->name,
                    'description' => $venue->description,
                    'address' => $venue->address,
                    'phone' => $venue->phone,
                ];
            })->values();
        }

        return Inertia::render('Event/Show', [
            'event' => $event,
            'images' => $event->images->map(function ($image) {
                return [
                    'id' => $image->id,
                    'path' => $image->url,
                    'alt' => $image->alt,
                    'sort_order' => $image->sort_order,
                ];
            }),
            'timeslots' => collect(),
            'shops' => $event->shops->map(function ($shop) {
                return [
                    'id' => $shop->id,
                    'name' => $shop->name,
                    'address' => $shop->address,
                    'phone' => $shop->phone,
                    'image_url' => $shop->image_url,
                ];
            }),
            'venues' => $venues,
            'isEnded' => false,
            'endDate' => null,
            'canReserve' => false,
            'showSuccess' => true, // 成功ページを表示するフラグ
            'successFormData' => $formData, // 送信データ
        ]);
    }

    /**
     * LINE通知を送信
     */
    private function sendLineNotification(Event $event, EventReservation $reservation)
    {
        $lineController = new LineWebhookController();
        
        // イベントに関連する店舗を取得
        $event->load('shops');
        $shops = $event->shops;
        
        // 店舗が関連付けられていない場合は、.envのLINE_GROUP_IDを使用（後方互換性）
        if ($shops->isEmpty()) {
            Log::warning('イベントに関連する店舗が見つかりません。.envのLINE_GROUP_IDを使用します。', [
                'event_id' => $event->id,
            ]);
            $this->sendLineNotificationToGroup($lineController, $event, $reservation, null);
            return;
        }
        
        // 各店舗のLINEグループIDに通知を送信
        foreach ($shops as $shop) {
            if (!empty($shop->line_group_id)) {
                try {
                    $this->sendLineNotificationToGroup($lineController, $event, $reservation, $shop->line_group_id);
                } catch (\Exception $e) {
                    Log::error('LINE通知の送信に失敗しました（店舗ID: ' . $shop->id . '）: ' . $e->getMessage(), [
                        'shop_id' => $shop->id,
                        'line_group_id' => $shop->line_group_id,
                        'reservation_id' => $reservation->id,
                    ]);
                }
            } else {
                Log::warning('店舗にLINEグループIDが設定されていません。', [
                    'shop_id' => $shop->id,
                    'shop_name' => $shop->name,
                ]);
            }
        }
    }
    
    /**
     * LINE通知を特定のグループIDに送信
     */
    private function sendLineNotificationToGroup(LineWebhookController $lineController, Event $event, EventReservation $reservation, $groupId = null)
    {
        // フォーム種別の日本語表示名を取得
        $formTypeNames = [
            'reservation' => '予約フォーム',
            'document' => '資料請求フォーム',
            'contact' => 'お問い合わせフォーム',
        ];
        $formTypeName = $formTypeNames[$event->form_type] ?? $event->form_type;

        // メッセージを構築
        $message = "━━━━━━━━━━━━━━━━\n";
        $message .= "📋 新しい{$formTypeName}が届きました\n";
        $message .= "━━━━━━━━━━━━━━━━\n\n";
        
        $message .= "🎯 イベント名: {$event->title}\n";
        $message .= "📝 フォーム種別: {$formTypeName}\n\n";
        
        $message .= "━━━━━━━━━━━━━━━━\n";
        $message .= "👤 お客様情報\n";
        $message .= "━━━━━━━━━━━━━━━━\n";
        $message .= "お名前: {$reservation->name}\n";
        
        if ($reservation->furigana) {
            $message .= "フリガナ: {$reservation->furigana}\n";
        }
        
        $message .= "メールアドレス: {$reservation->email}\n";
        $message .= "電話番号: {$reservation->phone}\n";
        
        if ($reservation->address) {
            $message .= "住所: {$reservation->address}\n";
        }
        
        if ($reservation->birth_date) {
            $message .= "生年月日: {$reservation->birth_date->format('Y年m月d日')}\n";
        }

        // フォーム種別に応じた詳細情報
        if ($event->form_type === 'reservation') {
            $message .= "\n━━━━━━━━━━━━━━━━\n";
            $message .= "📅 予約情報\n";
            $message .= "━━━━━━━━━━━━━━━━\n";
            
            if ($reservation->reservation_datetime) {
                $datetime = \Carbon\Carbon::parse($reservation->reservation_datetime);
                $message .= "予約日時: {$datetime->format('Y年m月d日 H:i')}\n";
            }
            
            if ($reservation->venue_id) {
                $venue = $reservation->venue;
                if ($venue) {
                    $message .= "会場: {$venue->name}\n";
                }
            }
            
            if ($reservation->seijin_year) {
                $message .= "成人年: {$reservation->seijin_year}年\n";
            }
            
            if ($reservation->has_visited_before !== null) {
                $visitedText = $reservation->has_visited_before ? 'あり' : 'なし';
                $message .= "来店経験: {$visitedText}\n";
            }
            
            if ($reservation->referred_by_name) {
                $message .= "紹介者: {$reservation->referred_by_name}\n";
            }
            
            if ($reservation->school_name) {
                $message .= "学校名: {$reservation->school_name}\n";
            }
            
            if ($reservation->staff_name) {
                $message .= "担当者名: {$reservation->staff_name}\n";
            }
            
            if ($reservation->visit_reasons && count($reservation->visit_reasons) > 0) {
                $reasons = implode('、', $reservation->visit_reasons);
                $message .= "来店動機: {$reasons}\n";
            }
            
            if ($reservation->parking_usage) {
                $message .= "駐車場利用: {$reservation->parking_usage}\n";
            }
            
            if ($reservation->parking_car_count) {
                $message .= "駐車台数: {$reservation->parking_car_count}台\n";
            }
            
            if ($reservation->considering_plans && count($reservation->considering_plans) > 0) {
                $plans = implode('、', $reservation->considering_plans);
                $message .= "検討プラン: {$plans}\n";
            }
        } elseif ($event->form_type === 'document') {
            $message .= "\n━━━━━━━━━━━━━━━━\n";
            $message .= "📦 資料請求情報\n";
            $message .= "━━━━━━━━━━━━━━━━\n";
            
            if ($reservation->request_method) {
                $message .= "希望方法: {$reservation->request_method}\n";
            }
            
            if ($reservation->postal_code) {
                $message .= "郵便番号: {$reservation->postal_code}\n";
            }
        }

        // 共通情報
        if ($reservation->heard_from) {
            $message .= "\n━━━━━━━━━━━━━━━━\n";
            $message .= "📢 認知経路\n";
            $message .= "━━━━━━━━━━━━━━━━\n";
            $message .= "{$reservation->heard_from}\n";
        }
        
        if ($reservation->inquiry_message) {
            $message .= "\n━━━━━━━━━━━━━━━━\n";
            $message .= "💬 お問い合わせ内容\n";
            $message .= "━━━━━━━━━━━━━━━━\n";
            $message .= "{$reservation->inquiry_message}\n";
        }

        $message .= "\n━━━━━━━━━━━━━━━━\n";
        $message .= "予約ID: #{$reservation->id}\n";
        $message .= "━━━━━━━━━━━━━━━━";

        // LINE通知を送信（グループIDを指定）
        $lineController->pushToLineGroup($message, $groupId);
    }

    /**
     * 受付完了メールを送信し、データベースに保存
     */
    private function sendReservationConfirmationEmail(EventReservation $reservation)
    {
        $reservation->load('event', 'document');
        
        // メールスレッドを取得または作成
        $emailThread = EmailThread::firstOrCreate(
            ['event_reservation_id' => $reservation->id],
            ['subject' => "【{$reservation->event->title}】ご予約ありがとうございます"]
        );

        // メールを送信（スレッドIDを渡す）
        $mailable = new ReservationConfirmationMail($reservation, $emailThread->id);
        Mail::to($reservation->email)->send($mailable);

        // 送信したメールをデータベースに保存
        // メールの生データを取得するため、Mailファサードのイベントを使用
        // または、送信後にメール内容を再構築して保存
        $rawEmail = $this->buildRawEmail($mailable, $reservation->email);
        
        Email::create([
            'message_id' => 'reservation-confirmation-' . $reservation->id . '-' . now()->timestamp,
            'from' => config('mail.from.address'),
            'to' => $reservation->email,
            'subject' => $mailable->envelope()->subject,
            'text_body' => view('mail.reservation_confirmation_plain', ['reservation' => $reservation])->render(),
            'html_body' => null,
            'raw_email' => $rawEmail,
            'event_reservation_id' => $reservation->id,
            'email_thread_id' => $emailThread->id,
        ]);

        Log::info('受付完了メールを送信しました', [
            'reservation_id' => $reservation->id,
            'email' => $reservation->email,
            'email_thread_id' => $emailThread->id,
        ]);
    }

    /**
     * メールの生データを構築
     */
    private function buildRawEmail($mailable, $to)
    {
        $envelope = $mailable->envelope();
        $content = $mailable->content();
        
        $subject = $envelope->subject;
        $from = config('mail.from.address');
        $fromName = config('mail.from.name');
        
        $textBody = view('mail.reservation_confirmation_plain', ['reservation' => $mailable->reservation])->render();
        
        $rawEmail = "From: {$fromName} <{$from}>\r\n";
        $rawEmail .= "To: {$to}\r\n";
        $rawEmail .= "Subject: {$subject}\r\n";
        $rawEmail .= "Reply-To: reply@reply.kyogofuku-hirata.jp\r\n";
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

