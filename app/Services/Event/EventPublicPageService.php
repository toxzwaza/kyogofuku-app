<?php

namespace App\Services\Event;

use App\Models\Event;
use App\Models\EventReservation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class EventPublicPageService
{
    /**
     * Inertia 用のイベント公開ページ props（Event/Show および LP テンプレ共通）
     *
     * @return array<string, mixed>
     */
    public function buildShowPayload(Event $event): array
    {
        $now = now();
        $today = $now->copy()->startOfDay();

        $isEnded = $event->end_at && $today->gt($event->end_at);
        $endDate = $isEnded ? $event->end_at->format('Y年n月j日') : null;

        $canReserve = !$event->start_at || $today->gte($event->start_at);
        $availableTimeslots = collect();

        if ($canReserve && !$isEnded) {
            $timeslots = $event->timeslots()
                ->where('is_active', true)
                ->whereDate('start_at', '>=', Carbon::today())
                ->orderBy('start_at', 'asc')
                ->get();

            $reservationCounts = [];
            if ($timeslots->isNotEmpty()) {
                $dateTimes = $timeslots->map(fn ($t) => $t->start_at->format('Y-m-d H:i:s'))->values()->all();
                $counts = EventReservation::where('event_id', $event->id)
                    ->where('cancel_flg', false)
                    ->whereIn('reservation_datetime', $dateTimes)
                    ->selectRaw('reservation_datetime, count(*) as cnt')
                    ->groupBy('reservation_datetime')
                    ->pluck('cnt', 'reservation_datetime')
                    ->all();
                $reservationCounts = $counts;
            }

            $availableTimeslots = $timeslots->map(function ($timeslot) use ($reservationCounts) {
                $dt = $timeslot->start_at->format('Y-m-d H:i:s');
                $reservationCount = $reservationCounts[$dt] ?? 0;
                $timeslot->remaining_capacity = max(0, $timeslot->capacity - $reservationCount);

                return $timeslot;
            })->values();
        }

        $images = $event->images->map(function ($image) {
            return [
                'id' => $image->id,
                'path' => $image->url,
                'webp_path' => $image->webp_url,
                'alt' => $image->alt,
                'sort_order' => $image->sort_order,
                'margin_top_px' => $image->margin_top_px,
                'margin_bottom_px' => $image->margin_bottom_px,
            ];
        });

        $slideshowPositions = DB::table('event_slideshow_positions')
            ->where('event_id', $event->id)
            ->orderBy('position')
            ->orderBy('sort_order')
            ->get()
            ->groupBy('position')
            ->map(function ($items) {
                return $items->map(function ($item) {
                    return [
                        'slideshow_id' => $item->slideshow_id,
                        'sort_order' => $item->sort_order,
                    ];
                })->toArray();
            })
            ->toArray();

        $slideshows = [];
        if (!empty($slideshowPositions)) {
            $allSlideshowIds = [];
            foreach ($slideshowPositions as $positionSlideshows) {
                foreach ($positionSlideshows as $item) {
                    $allSlideshowIds[] = $item['slideshow_id'];
                }
            }

            $slideshowModels = \App\Models\Slideshow::with('images')
                ->whereIn('id', array_unique($allSlideshowIds))
                ->get();

            foreach ($slideshowModels as $slideshow) {
                $slideshows[$slideshow->id] = [
                    'id' => $slideshow->id,
                    'name' => $slideshow->name,
                    'type' => $slideshow->type ?? 'fade',
                    'autoplay_interval' => $slideshow->autoplay_interval ?? 5000,
                    'autoplay_enabled' => $slideshow->autoplay_enabled ?? true,
                    'fullscreen' => $slideshow->fullscreen ?? true,
                    'images' => $slideshow->images->map(function ($image) {
                        return [
                            'id' => $image->id,
                            'path' => $image->url,
                            'alt' => $image->alt,
                            'sort_order' => $image->sort_order,
                        ];
                    })->sortBy('sort_order')->values()->toArray(),
                ];
            }
        }

        $shops = $event->shops->map(function ($shop) {
            return [
                'id' => $shop->id,
                'name' => $shop->name,
                'address' => $shop->address,
                'phone' => $shop->phone,
                'image' => $shop->image,
                'image_url' => $shop->image_url,
            ];
        });

        $venues = [];
        if ($event->usesTimeslotReservation()) {
            $lastSlotDatesByVenue = $event->timeslots
                ->where('is_active', true)
                ->groupBy('venue_id')
                ->map(fn ($slots) => $slots->max('start_at'));
            $venues = $event->venues
                ->where('is_active', true)
                ->sortBy(function ($venue) use ($lastSlotDatesByVenue) {
                    $last = $lastSlotDatesByVenue->get($venue->id);

                    return $last ?? Carbon::createFromDate(9999, 12, 31);
                })
                ->values()
                ->map(function ($venue) use ($event) {
                    $dates = $event->timeslots
                        ->where('is_active', true)
                        ->where('venue_id', $venue->id)
                        ->map(fn ($t) => Carbon::parse($t->start_at)->format('Y-m-d'))
                        ->unique()
                        ->sort()
                        ->values()
                        ->toArray();

                    return [
                        'id' => $venue->id,
                        'name' => $venue->name,
                        'description' => $venue->description,
                        'address' => $venue->address,
                        'phone' => $venue->phone,
                        'image_url' => $venue->image_url,
                        'dates' => array_values($dates),
                    ];
                })
                ->values();
        }

        $documents = [];
        if ($event->form_type === 'document') {
            $documents = $event->documents->load('images')->map(function ($document) {
                return [
                    'id' => $document->id,
                    'name' => $document->name,
                    'description' => $document->description,
                    'thumbnail_url' => $document->thumbnail_url,
                ];
            })->values();
        }

        $ctaButtonPositions = DB::table('event_cta_button_positions')
            ->where('event_id', $event->id)
            ->orderBy('position')
            ->pluck('position')
            ->values()
            ->toArray();

        return [
            'event' => $event,
            'images' => $images,
            'slideshowPositions' => $slideshowPositions,
            'slideshows' => $slideshows,
            'ctaButtonPositions' => $ctaButtonPositions,
            'timeslots' => $availableTimeslots,
            'shops' => $shops,
            'venues' => $venues,
            'documents' => $documents,
            'isEnded' => $isEnded,
            'endDate' => $endDate,
            'canReserve' => $canReserve,
        ];
    }
}
