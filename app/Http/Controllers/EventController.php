<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventUtmTracking;
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
            ->firstOrFail();

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
            $availableTimeslots = $event->timeslots()
                ->where('is_active', true)
                ->get()
                ->map(function ($timeslot) use ($event) {
                    $reservationCount = $event->reservations()
                        ->where('reservation_datetime', $timeslot->start_at->format('Y-m-d H:i:s'))
                        ->count();
                    $timeslot->remaining_capacity = max(0, $timeslot->capacity - $reservationCount);
                    return $timeslot;
                })
                ->filter(function ($timeslot) {
                    return $timeslot->remaining_capacity > 0;
                })
                ->values();
        }

        // 画像のURLを変換
        $images = $event->images->map(function ($image) {
            return [
                'id' => $image->id,
                'path' => $image->url,
                'alt' => $image->alt,
                'sort_order' => $image->sort_order,
            ];
        });

        // スライドショー位置情報を取得
        $slideshowPositions = \Illuminate\Support\Facades\DB::table('event_slideshow_positions')
            ->where('event_id', $event->id)
            ->get()
            ->mapWithKeys(function ($item) {
                return [$item->position => $item->slideshow_id];
            })
            ->toArray();

        // スライドショー情報を取得
        $slideshows = [];
        if (!empty($slideshowPositions)) {
            $slideshowIds = array_values($slideshowPositions);
            $slideshowModels = \App\Models\Slideshow::with('images')->whereIn('id', $slideshowIds)->get();
            foreach ($slideshowModels as $slideshow) {
                $slideshows[$slideshow->id] = [
                    'id' => $slideshow->id,
                    'name' => $slideshow->name,
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
                    'image_url' => $venue->image_url,
                ];
            })->values();
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

        // UTMパラメータがある場合、トラッキングレコードを作成
        $request = request();
        if ($request->hasAny(['utm_source', 'utm_medium', 'utm_campaign', 'utm_id'])) {
            EventUtmTracking::create([
                'session_id' => $request->session()->getId(),
                'event_id' => $event->id,
                'utm_source' => $request->input('utm_source'),
                'utm_medium' => $request->input('utm_medium'),
                'utm_campaign' => $request->input('utm_campaign'),
                'utm_id' => $request->input('utm_id'),
            ]);
        }

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

