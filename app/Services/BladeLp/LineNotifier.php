<?php

namespace App\Services\BladeLp;

use App\Http\Controllers\LineWebhookController;
use App\Models\Event;
use App\Models\EventReservation;
use Illuminate\Support\Facades\Log;

/**
 * Blade LP 経由の予約に対する LINE グループ通知。
 *
 * 既存 EventReservationController::sendLineNotification() と同じ送信動線
 * （shops の line_group_id に対して投稿）を採用しつつ、本文は
 * form_schema / form_data 駆動で動的生成する。
 */
class LineNotifier
{
    /**
     * 紐づく shops の line_group_id それぞれにメッセージを送信する。
     */
    public function notify(Event $event, EventReservation $reservation): void
    {
        $event->loadMissing(['shops', 'venue']);
        $shops = $event->shops;
        $line = new LineWebhookController;

        $message = $this->buildMessage($event, $reservation);
        $detailUrl = route('admin.reservations.show', $reservation->id, true);

        if ($shops->isEmpty()) {
            try {
                $line->pushToLineGroup($message, null, $detailUrl, '予約詳細を開く');
            } catch (\Throwable $e) {
                Log::error('[BladeLp/LineNotifier] LINE通知失敗 (no shop)', [
                    'reservation_id' => $reservation->id,
                    'error' => $e->getMessage(),
                ]);
            }
            return;
        }

        $sent = [];
        foreach ($shops as $shop) {
            $gid = $shop->line_group_id ?? null;
            if (!$gid || in_array($gid, $sent, true)) {
                continue;
            }
            try {
                $line->pushToLineGroup($message, $gid, $detailUrl, '予約詳細を開く');
                $sent[] = $gid;
            } catch (\Throwable $e) {
                Log::error('[BladeLp/LineNotifier] LINE通知失敗', [
                    'reservation_id' => $reservation->id,
                    'shop_id' => $shop->id,
                    'line_group_id' => $gid,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * 予約内容を form_schema / form_data から組み立てる。
     */
    protected function buildMessage(Event $event, EventReservation $reservation): string
    {
        $schema = is_array($event->form_schema) ? $event->form_schema : [];
        $data = is_array($reservation->form_data) ? $reservation->form_data : [];

        $msg  = "━━━━━━━━━━━━━━━━\n";
        $msg .= "📋 新しい予約が届きました\n";
        $msg .= "━━━━━━━━━━━━━━━━\n\n";
        $msg .= "🎯 イベント: {$event->title}\n";
        if (!empty($reservation->venue?->name)) {
            $msg .= "📍 会場: {$reservation->venue->name}\n";
        }
        if (!empty($reservation->reservation_datetime)) {
            $dt = \Carbon\Carbon::parse($reservation->reservation_datetime);
            $msg .= "📅 ご来場日時: ".$dt->format('Y年n月j日 H:i')."\n";
        }
        $msg .= "\n";

        $msg .= "━━━━━━━━━━━━━━━━\n";
        $msg .= "👤 お客様情報\n";
        $msg .= "━━━━━━━━━━━━━━━━\n";
        $msg .= "お名前: {$reservation->name}\n";
        if (!empty($data['furigana'])) {
            $msg .= "フリガナ: {$data['furigana']}\n";
        }
        if (!empty($reservation->phone)) {
            $msg .= "電話番号: {$reservation->phone}\n";
        }
        if (!empty($reservation->email)) {
            $msg .= "メール: {$reservation->email}\n";
        }

        // form_data の中で「お客様情報」「予約日時」と重複しないものを「フォーム回答」セクションへ
        $skipKeys = [
            'name', 'furigana', 'email', 'phone', 'address', 'birth_date',
            'visit_slot', 'visit_slot_label',
        ];
        $extras = [];
        foreach ($schema as $field) {
            $key = $field['key'] ?? null;
            if (!$key || in_array($key, $skipKeys, true)) continue;
            if (($field['type'] ?? '') === 'hidden') continue;
            if (!array_key_exists($key, $data)) continue;
            $value = $data[$key];
            if ($value === null || $value === '' || (is_array($value) && count($value) === 0)) continue;

            // 表示用整形
            if (is_array($value)) {
                $rendered = implode('、', $value);
            } elseif (($field['type'] ?? '') === 'checkbox' && empty($field['options'])) {
                $rendered = ($value === '1' || $value === 1 || $value === true) ? 'はい' : 'いいえ';
            } else {
                $rendered = (string) $value;
            }
            $label = $field['label'] ?? $key;
            $extras[] = "{$label}: {$rendered}";
        }

        if ($extras !== []) {
            $msg .= "\n━━━━━━━━━━━━━━━━\n";
            $msg .= "📝 フォーム回答\n";
            $msg .= "━━━━━━━━━━━━━━━━\n";
            $msg .= implode("\n", $extras)."\n";
        }

        $msg .= "\n━━━━━━━━━━━━━━━━\n";
        $msg .= "予約ID: #{$reservation->id}\n";
        $msg .= '━━━━━━━━━━━━━━━━';
        return $msg;
    }
}
