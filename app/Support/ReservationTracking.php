<?php

namespace App\Support;

use App\Models\Event;
use App\Models\EventReservation;
use Illuminate\Http\Request;

/**
 * 予約完了を計測（GTM の dataLayer）へ渡すための値。
 *
 * 予約保存時に session へ1回だけ（flash）入れ、完了ページで取り出して
 * dataLayer.push({event: 'reservation_complete', ...}) に使う。
 * 完了ページを再読み込みしても二重に数えないよう、flash で1回だけにしている。
 * 個人情報（名前・電話・メール）は入れない。
 */
class ReservationTracking
{
    public const EVENT_NAME = 'reservation_complete';

    private const SESSION_KEY = 'reservation_complete_tracking';

    public static function flash(Request $request, Event $event, EventReservation $reservation): void
    {
        $event->loadMissing('shops');
        $reservation->loadMissing('venue');

        $request->session()->flash(self::SESSION_KEY, [
            'event' => self::EVENT_NAME,
            'reservation_id' => $reservation->id,
            'event_id' => $event->id,
            'event_slug' => $event->slug,
            'form_type' => $event->form_type,
            'brand' => LpBrand::keyForEvent($event),
            'shop' => $event->shops->pluck('name')->filter()->implode('・') ?: null,
            'venue' => $reservation->venue?->name,
            'utm_source' => $reservation->utm_source,
            'utm_medium' => $reservation->utm_medium,
            'utm_campaign' => $reservation->utm_campaign,
        ]);
    }

    /**
     * 完了ページ用。そのイベントの予約完了直後のときだけ値を返す。
     *
     * @return array<string, mixed>|null
     */
    public static function pull(Request $request, Event $event): ?array
    {
        $data = $request->session()->get(self::SESSION_KEY);
        if (!is_array($data) || (int) ($data['event_id'] ?? 0) !== (int) $event->id) {
            return null;
        }

        return $data;
    }
}
