<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BladeLpController extends Controller
{
    /**
     * Blade テンプレ方式の公開イベント LP を表示
     */
    public function show(Request $request, Event $event)
    {
        if (!$event->is_public) {
            abort(404);
        }

        $now = now();
        $today = $now->copy()->startOfDay();
        if ($event->start_at && $today->lt($event->start_at)) {
            abort(404);
        }

        $view = $event->bladeLpView();
        if (!$view) {
            abort(404);
        }

        $utmSource = $request->input('utm_source') ?: 'NONE';
        $request->session()->put('event_utm_sources.'.$event->id, $utmSource);

        $isEnded = $event->end_at && Carbon::today()->gt($event->end_at);

        $event->load(['images', 'shops', 'venues']);

        return view($view, [
            'event' => $event,
            'formSchema' => is_array($event->form_schema) ? $event->form_schema : [],
            'isEnded' => $isEnded,
            'utmSource' => $utmSource,
        ]);
    }
}
