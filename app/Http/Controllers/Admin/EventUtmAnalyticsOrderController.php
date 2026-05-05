<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use App\Http\Controllers\Concerns\ResolvesUiView;
use Inertia\Inertia;

class EventUtmAnalyticsOrderController extends Controller
{
    use ResolvesUiView;

    /**
     * UTM分析APIに含めるイベントの並び順管理画面を表示
     */
    public function index()
    {
        $events = Event::where('utm_analytics_enabled', true)
            ->orderByRaw('COALESCE(utm_analytics_sort_order, 999999) ASC')
            ->orderByDesc('created_at')
            ->get(['id', 'title', 'utm_analytics_sort_order']);

        return Inertia::render($this->viewFor('Admin/Event/UtmAnalyticsOrder'), [
            'events' => $events,
        ]);
    }

    /**
     * 並び順を保存
     */
    public function updateOrder(Request $request)
    {
        $validated = $request->validate([
            'event_ids' => 'required|array',
            'event_ids.*' => 'exists:events,id',
        ]);

        foreach ($validated['event_ids'] as $index => $eventId) {
            Event::where('id', $eventId)->update([
                'utm_analytics_sort_order' => $index + 1,
            ]);
        }

        return redirect()->route('admin.events.utm-analytics-order')
            ->with('success', '並び順を保存しました。');
    }
}
