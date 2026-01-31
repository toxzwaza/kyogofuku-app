<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventUtmTracking;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EventController extends Controller
{
    /**
     * イベント紹介ページを表示
     */
    public function show(string $slug)
    {
        $event = Event::with(['images', 'timeslots', 'shops', 'venues', 'documents'])
            ->where('slug', $slug)
            ->where('is_public', true)
            ->first();

        // メイン slug で見つからない場合、エイリアスで検索してリダイレクト
        if (!$event) {
            $eventByAlias = Event::where('is_public', true)
                ->whereJsonContains('slug_aliases', $slug)
                ->first();

            if ($eventByAlias) {
                $url = route('event.show', ['slug' => (string) $eventByAlias->slug]);
                $query = request()->query();
                if (!empty($query)) {
                    $url .= '?' . http_build_query($query);
                }
                return redirect($url);
            }

            abort(404);
        }

        // 公開期間チェック
        $now = now();
        $today = $now->startOfDay();
        if ($event->start_at && $today->lt($event->start_at)) {
            abort(404);
        }
        // end_atを過ぎてもアクセスは可能（終了メッセージを表示するため）

        // イベントが終了しているかチェック（先に定義）
        $isEnded = $event->end_at && $today->gt($event->end_at);
        $endDate = $isEnded ? $event->end_at->format('Y年n月j日') : null;

        // start_atを過ぎた日より予約受付が可能
        $canReserve = !$event->start_at || $today->gte($event->start_at);
        $availableTimeslots = collect();
        
        if ($canReserve && !$isEnded) {
            // 全枠を表示（満枠も含む）。本日より前の枠は受付終了のため表示しない
            $availableTimeslots = $event->timeslots()
                ->where('is_active', true)
                ->whereDate('start_at', '>=', Carbon::today())
                ->orderBy('start_at', 'asc')
                ->get()
                ->map(function ($timeslot) use ($event) {
                    $reservationCount = $event->reservations()
                        ->where('cancel_flg', false)
                        ->where('reservation_datetime', $timeslot->start_at->format('Y-m-d H:i:s'))
                        ->count();
                    $timeslot->remaining_capacity = max(0, $timeslot->capacity - $reservationCount);
                    return $timeslot;
                })
                ->values();
        }

        // 画像のURLを変換
        $images = $event->images->map(function ($image) {
            return [
                'id' => $image->id,
                'path' => $image->url,
                'webp_path' => $image->webp_url,
                'alt' => $image->alt,
                'sort_order' => $image->sort_order,
            ];
        });

        // スライドショー位置情報を取得（複数のスライドショーに対応）
        $slideshowPositions = \Illuminate\Support\Facades\DB::table('event_slideshow_positions')
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

        // スライドショー情報を取得
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

        // 店舗の画像URLを変換
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

        // 会場情報（予約フォームの場合のみ・各会場の予約枠の最終日が直近のものから昇順）
        $venues = [];
        if ($event->form_type === 'reservation') {
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

        // 資料情報（資料請求フォームの場合のみ）
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

        // UTMトラッキングレコードを作成（UTMパラメータがない場合はHPを補填）
        $request = request();
        EventUtmTracking::create([
            'session_id' => $request->session()->getId(),
            'event_id' => $event->id,
            'utm_source' => $request->input('utm_source') ?: 'NONE',
            'utm_medium' => $request->input('utm_medium'),
            'utm_campaign' => $request->input('utm_campaign'),
            'utm_id' => $request->input('utm_id'),
        ]);

        return Inertia::render('Event/Show', [
            'event' => $event,
            'images' => $images,
            'slideshowPositions' => $slideshowPositions,
            'slideshows' => $slideshows,
            'timeslots' => $availableTimeslots,
            'shops' => $shops,
            'venues' => $venues,
            'documents' => $documents,
            'isEnded' => $isEnded,
            'endDate' => $endDate,
            'canReserve' => $canReserve,
        ]);
    }
}

