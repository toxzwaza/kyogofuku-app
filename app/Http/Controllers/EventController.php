<?php

namespace App\Http\Controllers;

use App\Http\Support\EventInertiaViewFactory;
use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(
        protected EventInertiaViewFactory $eventInertia,
    ) {}

    /**
     * イベント紹介ページを表示
     */
    public function show(Request $request, string $slug)
    {
        $event = $this->resolvePublicEventBySlug($slug, 'event.show');
        if ($event instanceof \Illuminate\Http\RedirectResponse) {
            return $event;
        }

        $utmSource = $request->input('utm_source') ?: 'NONE';
        $request->session()->put('event_utm_sources.'.$event->id, $utmSource);

        return $this->eventInertia->showResponse($event);
    }

    /**
     * LP テンプレ有効時の予約専用ページ
     */
    public function reserve(Request $request, string $slug)
    {
        $event = $this->resolvePublicEventBySlug($slug, 'event.reserve.page');
        if ($event instanceof \Illuminate\Http\RedirectResponse) {
            return $event;
        }

        if (!$event->activeLpDesignSlug()) {
            return redirect()->route('event.show', ['slug' => $event->slug]);
        }

        if (!$event->usesTimeslotReservation()) {
            return redirect()->route('event.show', ['slug' => $event->slug]);
        }

        $today = Carbon::today();
        $isEnded = $event->end_at && $today->gt($event->end_at);
        if ($isEnded) {
            return redirect()->route('event.show', ['slug' => $event->slug]);
        }

        $utmSource = $request->input('utm_source') ?: 'NONE';
        $request->session()->put('event_utm_sources.'.$event->id, $utmSource);

        return $this->eventInertia->reserveResponse($event);
    }

    /**
     * @return Event|\Illuminate\Http\RedirectResponse
     */
    private function resolvePublicEventBySlug(string $slug, string $canonicalRouteName)
    {
        $event = Event::with(['images', 'timeslots', 'shops', 'venues', 'documents'])
            ->where('slug', $slug)
            ->where('is_public', true)
            ->first();

        if (!$event) {
            $eventByAlias = Event::where('is_public', true)
                ->whereJsonContains('slug_aliases', $slug)
                ->first();

            if ($eventByAlias) {
                $url = route($canonicalRouteName, ['slug' => (string) $eventByAlias->slug]);
                $query = request()->query();
                if (!empty($query)) {
                    $url .= '?'.http_build_query($query);
                }

                return redirect($url);
            }

            abort(404);
        }

        $now = now();
        $today = $now->copy()->startOfDay();
        if ($event->start_at && $today->lt($event->start_at)) {
            abort(404);
        }

        return $event;
    }
}
