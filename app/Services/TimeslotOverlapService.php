<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventTimeslot;

/**
 * 同じ店舗の別予約イベントとの「重なり」判定（期間一括作成と予約枠一覧で共通）
 *
 * 判定は別イベントの枠が実際にある会場で行う:
 *   - 同じ会場に枠がある → 重なり
 *   - このイベントの会場以外（催事会場など）に枠がある → 重なり（店舗スタッフが出払う想定）
 *   - このイベントの別の会場にだけ枠がある → その会場以外は重ならない
 */
class TimeslotOverlapService
{
    /**
     * 同じ店舗に紐付く別の予約イベントの有効な枠: date => [event_id => {title, venue_ids, venue_names}]
     */
    public function otherEventSlots(Event $event, string $startStr, string $endStr): array
    {
        $shopIds = $event->shops()->withoutGlobalScope('visibleGroup')->pluck('shops.id');
        if ($shopIds->isEmpty()) {
            return [];
        }

        $eventIds = Event::whereIn('form_type', Event::TIMESLOT_RESERVATION_FORM_TYPES)
            ->where('id', '!=', $event->id)
            ->whereHas('shops', fn ($q) => $q->withoutGlobalScope('visibleGroup')->whereIn('shops.id', $shopIds))
            ->pluck('title', 'id');

        $result = [];
        EventTimeslot::whereIn('event_id', $eventIds->keys())
            ->where('is_active', true)
            ->whereBetween('start_at', [$startStr.' 00:00:00', $endStr.' 23:59:59'])
            ->with('venue:id,name')
            ->get(['event_id', 'venue_id', 'start_at'])
            ->each(function ($t) use (&$result, $eventIds) {
                $date = $t->start_at->format('Y-m-d');
                $entry = $result[$date][$t->event_id] ?? ['event_id' => $t->event_id, 'title' => $eventIds[$t->event_id], 'venue_ids' => [], 'venue_names' => []];
                if (! in_array($t->venue_id, $entry['venue_ids'])) {
                    $entry['venue_ids'][] = $t->venue_id;
                    $entry['venue_names'][] = $t->venue?->name ?? '会場なし';
                }
                $result[$date][$t->event_id] = $entry;
            });

        return $result;
    }

    public function conflictsFor(array $otherEvents, ?int $venueId, array $eventVenueIds): array
    {
        $conflicts = [];
        foreach ($otherEvents as $other) {
            $sameVenue = $venueId !== null && in_array($venueId, $other['venue_ids']);
            $outsideVenues = ! array_intersect($other['venue_ids'], $eventVenueIds);
            if ($sameVenue || $outsideVenues || $venueId === null) {
                $conflicts[] = [
                    'event_id' => $other['event_id'],
                    'title' => $other['title'],
                    'venue_names' => $other['venue_names'],
                    'reason' => $sameVenue ? '同じ会場に枠あり' : '同じ店舗の別イベント',
                ];
            }
        }

        return $conflicts;
    }
}
