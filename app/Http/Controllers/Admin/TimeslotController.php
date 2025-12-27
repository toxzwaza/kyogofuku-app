<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventTimeslot;
use App\Models\Venue;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TimeslotController extends Controller
{
    /**
     * 予約枠一覧を表示
     */
    public function index(Event $event)
    {
        $timeslots = EventTimeslot::where('event_id', $event->id)
            ->orderBy('start_at', 'asc')
            ->get()
            ->map(function ($timeslot) use ($event) {
                $reservationCount = $event->reservations()
                    ->where('reservation_datetime', $timeslot->start_at->format('Y-m-d H:i:s'))
                    ->count();
                $timeslot->remaining_capacity = max(0, $timeslot->capacity - $reservationCount);
                return $timeslot;
            });

        return Inertia::render('Admin/Timeslot/Index', [
            'event' => $event,
            'timeslots' => $timeslots,
        ]);
    }

    /**
     * 予約枠追加フォームを表示
     */
    public function create(Event $event)
    {
        $event->load('venues');
        $venues = $event->venues;

        return Inertia::render('Admin/Timeslot/Create', [
            'event' => $event,
            'venues' => $venues,
        ]);
    }

    /**
     * 予約枠を保存
     */
    public function store(Request $request, Event $event)
    {
        $event->load('venues');
        
        $validated = $request->validate([
            'venue_id' => 'nullable|exists:venues,id',
            'start_at' => 'required|date',
            'capacity' => 'required|integer|min:1',
            'is_active' => 'boolean',
        ]);

        // 会場が1つしかない場合は自動選択
        if (empty($validated['venue_id']) && $event->venues->count() === 1) {
            $validated['venue_id'] = $event->venues->first()->id;
        }

        // 選択された会場がイベントに関連付けられているか確認
        if (!empty($validated['venue_id']) && !$event->venues->contains('id', $validated['venue_id'])) {
            return redirect()->back()
                ->withErrors(['venue_id' => '選択された会場はこのイベントに関連付けられていません。']);
        }

        $validated['event_id'] = $event->id;
        $validated['is_active'] = $request->has('is_active') ? $request->is_active : true;

        EventTimeslot::create($validated);

        return redirect()->route('admin.events.timeslots.index', $event->id)
            ->with('success', '予約枠を追加しました。');
    }

    /**
     * 予約枠編集フォームを表示
     */
    public function edit(EventTimeslot $timeslot)
    {
        $timeslot->load('event', 'venue');
        $timeslot->event->load('venues');
        $venues = $timeslot->event->venues;
        
        $reservationCount = $timeslot->event->reservations()
            ->where('reservation_datetime', $timeslot->start_at->format('Y-m-d H:i:s'))
            ->count();
        $timeslot->remaining_capacity = max(0, $timeslot->capacity - $reservationCount);

        return Inertia::render('Admin/Timeslot/Edit', [
            'timeslot' => $timeslot,
            'venues' => $venues,
        ]);
    }

    /**
     * 予約枠を更新
     */
    public function update(Request $request, EventTimeslot $timeslot)
    {
        $timeslot->load('event');
        $timeslot->event->load('venues');
        
        $validated = $request->validate([
            'venue_id' => 'nullable|exists:venues,id',
            'start_at' => 'required|date',
            'capacity' => 'required|integer|min:1',
            'is_active' => 'boolean',
        ]);

        // 会場が1つしかない場合は自動選択
        if (empty($validated['venue_id']) && $timeslot->event->venues->count() === 1) {
            $validated['venue_id'] = $timeslot->event->venues->first()->id;
        }

        // 選択された会場がイベントに関連付けられているか確認
        if (!empty($validated['venue_id']) && !$timeslot->event->venues->contains('id', $validated['venue_id'])) {
            return redirect()->back()
                ->withErrors(['venue_id' => '選択された会場はこのイベントに関連付けられていません。']);
        }

        // 既存の予約数を超えないようにcapacityをチェック
        $reservationCount = $timeslot->event->reservations()
            ->where('reservation_datetime', $timeslot->start_at->format('Y-m-d H:i:s'))
            ->count();

        if ($validated['capacity'] < $reservationCount) {
            return redirect()->back()
                ->withErrors(['capacity' => "既存の予約数（{$reservationCount}件）を下回ることはできません。"]);
        }

        $validated['is_active'] = $request->has('is_active') ? $request->is_active : $timeslot->is_active;

        $timeslot->update($validated);

        return redirect()->route('admin.events.timeslots.index', $timeslot->event_id)
            ->with('success', '予約枠を更新しました。');
    }

    /**
     * 予約枠を削除
     */
    public function destroy(EventTimeslot $timeslot)
    {
        $eventId = $timeslot->event_id;
        
        // 既存の予約がある場合は削除不可
        $reservationCount = $timeslot->event->reservations()
            ->where('reservation_datetime', $timeslot->start_at->format('Y-m-d H:i:s'))
            ->count();

        if ($reservationCount > 0) {
            return redirect()->back()
                ->withErrors(['error' => "予約が存在するため削除できません。（予約数: {$reservationCount}件）"]);
        }

        $timeslot->delete();

        return redirect()->route('admin.events.timeslots.index', $eventId)
            ->with('success', '予約枠を削除しました。');
    }

    /**
     * 予約枠の定員を増減
     */
    public function adjustCapacity(Request $request, EventTimeslot $timeslot)
    {
        $validated = $request->validate([
            'amount' => 'required|integer', // 増減数（正の数で増加、負の数で減少）
        ]);

        $newCapacity = $timeslot->capacity + $validated['amount'];

        // 最小値は1
        if ($newCapacity < 1) {
            return response()->json([
                'success' => false,
                'message' => '定員は1以上である必要があります。',
            ], 400);
        }

        // 既存の予約数を超えないようにチェック（減少の場合）
        if ($validated['amount'] < 0) {
            $reservationCount = $timeslot->event->reservations()
                ->where('reservation_datetime', $timeslot->start_at->format('Y-m-d H:i:s'))
                ->count();

            if ($newCapacity < $reservationCount) {
                return response()->json([
                    'success' => false,
                    'message' => "既存の予約数（{$reservationCount}件）を下回ることはできません。",
                ], 400);
            }
        }

        $timeslot->update(['capacity' => $newCapacity]);

        // 更新後の情報を返す
        $reservationCount = $timeslot->event->reservations()
            ->where('reservation_datetime', $timeslot->start_at->format('Y-m-d H:i:s'))
            ->count();

        return response()->json([
            'success' => true,
            'message' => '定員を更新しました。',
            'capacity' => $newCapacity,
            'reserved' => $reservationCount,
            'remaining' => max(0, $newCapacity - $reservationCount),
        ]);
    }
}

