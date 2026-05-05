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

        // Blade テンプレ方式の LP は事前告知用途を前提とするため、
        // start_at が未来日付でも公開する（is_public のみで制御）。

        $view = $event->bladeLpView();
        if (!$view) {
            abort(404);
        }

        $utmSource = $request->input('utm_source') ?: 'NONE';
        $request->session()->put('event_utm_sources.'.$event->id, $utmSource);

        $isEnded = $event->end_at && Carbon::today()->gt($event->end_at);

        $event->load(['images', 'shops', 'venues', 'timeslots']);
        // 予約枠の残席計算用に、現在予約中（キャンセル除く）の予約のみロード
        $event->load(['reservations' => function ($q) {
            $q->where('cancel_flg', false);
        }]);

        return view($view, [
            'event' => $event,
            'formSchema' => is_array($event->form_schema) ? $event->form_schema : [],
            'isEnded' => $isEnded,
            'utmSource' => $utmSource,
        ]);
    }
}
