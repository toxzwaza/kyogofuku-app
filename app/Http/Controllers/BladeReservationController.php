<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventReservation;
use App\Models\EventTimeslot;
use App\Services\BladeLp\FormSchemaValidator;
use App\Services\BladeLp\LineNotifier;
use App\Services\EventReservationScheduleBootstrapService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Blade テンプレ方式の LP 用予約コントローラ。
 *
 * 既存 EventReservationController は固定カラムベース（form_type ごと）の予約を扱う。
 * こちらは events.form_schema に基づいた動的フォームを扱い、
 * 全回答を event_reservations.form_data にまとめて保存する。
 * 検索・既存運用整合のため、name / email / phone は固定カラムにも複製する。
 */
class BladeReservationController extends Controller
{
    public function __construct(
        protected FormSchemaValidator $schemaValidator,
    ) {}

    public function store(Request $request, Event $event)
    {
        if (!$event->is_public || !$event->usesBladeLp()) {
            abort(404);
        }

        $schema = is_array($event->form_schema) ? $event->form_schema : [];
        if ($schema === []) {
            abort(422, 'このイベントにはフォーム定義（form_schema）が設定されていません。');
        }

        $rules = $this->schemaValidator->rulesFromSchema($schema);
        $attrs = $this->schemaValidator->attributesFromSchema($schema);

        $messages = [
            'accepted' => ':attribute にチェックを入れてください。',
            'required' => ':attribute は必須項目です。',
            'email'    => ':attribute は有効なメールアドレスを入力してください。',
        ];

        $validator = Validator::make($request->all(), $rules, $messages, $attrs);
        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $validated = $validator->validated();

        // 検索用に標準キー（name/email/phone）が含まれていれば複製する
        $name  = is_string($validated['name']  ?? null) ? $validated['name']  : null;
        $email = is_string($validated['email'] ?? null) ? $validated['email'] : null;
        $phone = is_string($validated['phone'] ?? null) ? $validated['phone'] : null;

        // venue_id (options_from='event_venues' の select) があれば確保
        $explicitVenueId = null;
        foreach ($schema as $f) {
            if (($f['type'] ?? '') === 'select'
                && ($f['options_from'] ?? null) === 'event_venues'
                && !empty($validated[$f['key']])) {
                $explicitVenueId = (int) $validated[$f['key']];
                break;
            }
        }

        // timeslot 型のフィールドがあれば、その値で予約枠検証 → 残席チェック → 日時/会場を導出
        $reservationDatetime = null;
        $venueId = $explicitVenueId;
        foreach ($schema as $f) {
            if (($f['type'] ?? '') !== 'timeslot') continue;
            $tsKey = $f['key'] ?? null;
            if (!$tsKey || empty($validated[$tsKey])) continue;

            $slot = EventTimeslot::where('event_id', $event->id)
                ->where('id', $validated[$tsKey])
                ->where('is_active', true)
                ->first();
            if (!$slot) {
                throw ValidationException::withMessages([
                    $tsKey => ['選択された予約枠が見つかりません。'],
                ]);
            }

            // venue 連動チェック: filter_by_venue_field 指定があれば、選択 venue とスロットの venue が一致する必要
            if (!empty($f['filter_by_venue_field']) && $explicitVenueId !== null) {
                if ((int) $slot->venue_id !== $explicitVenueId) {
                    throw ValidationException::withMessages([
                        $tsKey => ['選択された店舗とご来店日時の組み合わせが正しくありません。'],
                    ]);
                }
            }

            $startAt = $slot->start_at->format('Y-m-d H:i:s');
            $countQ = EventReservation::where('event_id', $event->id)
                ->where('cancel_flg', false)
                ->where('reservation_datetime', $startAt);
            if ($slot->venue_id) {
                $countQ->where('venue_id', $slot->venue_id);
            } else {
                $countQ->whereNull('venue_id');
            }
            if ($countQ->count() >= $slot->capacity) {
                throw ValidationException::withMessages([
                    $tsKey => ['この予約枠は満席です。別の時間帯をお選びください。'],
                ]);
            }

            $reservationDatetime = $startAt;
            $venueId = $slot->venue_id; // スロットの venue_id を最終値として採用
            $validated[$tsKey.'_label'] = $slot->start_at->format('Y/n/j（D） H:i');
            break;
        }

        $reservation = EventReservation::create([
            'event_id'             => $event->id,
            'name'                 => $name  ?? '',
            'email'                => $email ?? '',
            'phone'                => $phone ?? '',
            'reservation_datetime' => $reservationDatetime,
            'venue_id'             => $venueId,
            'form_data'            => $validated,
            'utm_source'           => $request->input('utm_source')
                ?? $request->session()->get('event_utm_sources.'.$event->id),
        ]);

        // Google カレンダー連携（既存 EventReservationController と同じ Service を呼ぶ）
        // reservation_datetime（visit_slot から導出）の予定のみ作成。bring_date は連携しない。
        try {
            $reservation->load(['event', 'venue']);
            app(EventReservationScheduleBootstrapService::class)->bootstrapIfApplicable($reservation);
        } catch (\Throwable $e) {
            Log::error('[BladeReservation] Google カレンダー連携に失敗', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);
        }

        // LINE グループ通知（紐づく shops の line_group_id すべてに送信）
        try {
            app(LineNotifier::class)->notify($event, $reservation);
        } catch (\Throwable $e) {
            Log::error('[BladeReservation] LINE 通知失敗', [
                'reservation_id' => $reservation->id,
                'error' => $e->getMessage(),
            ]);
        }

        $request->session()->put('blade_lp_form_data.'.$event->id, $validated);

        // Vue 版と同じ event.reserve.success ルートにリダイレクトする。
        // success() 側で usesBladeLp() を判定して Blade テンプレを返す。
        if ($event->success_text) {
            return redirect()->route('event.reserve.success', [$event->id, $event->success_text])
                ->with('reservation_id', $reservation->id);
        }

        return redirect()->route('event.reserve.success', $event->id)
            ->with('reservation_id', $reservation->id);
    }
}
