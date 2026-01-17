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
            ->with('venue')
            ->orderBy('start_at', 'asc')
            ->get()
            ->map(function ($timeslot) use ($event) {
                // 予約を取得（会場IDと時間が一致するもののみ）
                $reservationQuery = $event->reservations()
                    ->where('reservation_datetime', $timeslot->start_at->format('Y-m-d H:i:s'));
                
                // 予約枠に会場IDが設定されている場合、同じ会場の予約のみ取得
                if ($timeslot->venue_id) {
                    $reservationQuery->where('venue_id', $timeslot->venue_id);
                } else {
                    // 予約枠に会場IDが設定されていない場合、venue_idがnullの予約のみ取得
                    $reservationQuery->whereNull('venue_id');
                }
                
                $reservationCount = $reservationQuery->count();
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
    public function create(Event $event, Request $request)
    {
        $event->load('venues');
        $venues = $event->venues;

        $duplicateTimeslot = null;
        if ($request->has('duplicate')) {
            $duplicateTimeslot = EventTimeslot::find($request->duplicate);
            // イベントIDが一致することを確認
            if ($duplicateTimeslot && $duplicateTimeslot->event_id === $event->id) {
                $duplicateTimeslot->load('venue');
            } else {
                $duplicateTimeslot = null;
            }
        }

        // 会場IDが指定されている場合、既存予約枠を取得
        $existingTimeslots = collect();
        if ($request->has('venue_id') && $request->venue_id) {
            $existingTimeslots = EventTimeslot::where('event_id', $event->id)
                ->where('venue_id', $request->venue_id)
                ->with('venue')
                ->orderBy('start_at', 'asc')
                ->get();
        }

        return Inertia::render('Admin/Timeslot/Create', [
            'event' => $event,
            'venues' => $venues,
            'duplicateTimeslot' => $duplicateTimeslot,
            'existingTimeslots' => $existingTimeslots,
        ]);
    }

    /**
     * 予約枠を保存（複数件一括登録対応）
     */
    public function store(Request $request, Event $event)
    {
        $event->load('venues');
        
        // 配列形式（一括登録）か単一形式（後方互換性）かを判定
        if ($request->has('timeslots') && is_array($request->timeslots)) {
            // 一括登録モード
            $validated = $request->validate([
                'timeslots' => 'required|array|min:1',
                'timeslots.*.venue_id' => 'nullable|exists:venues,id',
                'timeslots.*.start_at' => 'required|date',
                'timeslots.*.capacity' => 'required|integer|min:1',
                'timeslots.*.is_active' => 'boolean',
            ]);

            $createdCount = 0;
            $skippedCount = 0;
            $errors = [];

            foreach ($validated['timeslots'] as $index => $timeslotData) {
                try {
                    // 会場が1つしかない場合は自動選択
                    if (empty($timeslotData['venue_id']) && $event->venues->count() === 1) {
                        $timeslotData['venue_id'] = $event->venues->first()->id;
                    }

                    // 選択された会場がイベントに関連付けられているか確認
                    if (!empty($timeslotData['venue_id']) && !$event->venues->contains('id', $timeslotData['venue_id'])) {
                        $skippedCount++;
                        $errors[] = ($index + 1) . "件目: 選択された会場はこのイベントに関連付けられていません。";
                        continue;
                    }

                    // 同じイベント、会場、開始日時の組み合わせが既に存在するかチェック
                    $existingTimeslot = EventTimeslot::where('event_id', $event->id)
                        ->where('start_at', $timeslotData['start_at']);
                    
                    if (!empty($timeslotData['venue_id'])) {
                        $existingTimeslot->where('venue_id', $timeslotData['venue_id']);
                    } else {
                        $existingTimeslot->whereNull('venue_id');
                    }
                    
                    $existing = $existingTimeslot->first();

                    if ($existing) {
                        $skippedCount++;
                        $startAtFormatted = date('Y-m-d H:i', strtotime($timeslotData['start_at']));
                        $errors[] = ($index + 1) . "件目: {$startAtFormatted} の予約枠は既に存在するためスキップしました。";
                        continue;
                    }

                    $timeslotData['event_id'] = $event->id;
                    $timeslotData['is_active'] = isset($timeslotData['is_active']) ? $timeslotData['is_active'] : true;

                    EventTimeslot::create($timeslotData);
                    $createdCount++;
                } catch (\Exception $e) {
                    $skippedCount++;
                    $errors[] = ($index + 1) . "件目: 登録に失敗しました。(" . $e->getMessage() . ")";
                }
            }

            $message = "{$createdCount}件の予約枠を追加しました。";
            if ($skippedCount > 0) {
                $message .= " {$skippedCount}件がスキップされました。";
            }

            $redirect = redirect()->route('admin.events.timeslots.index', $event->id)
                ->with('success', $message);

            if (!empty($errors)) {
                // Inertiaはerrorsキーに対してMessageBagを期待するため、別のキーでエラーメッセージを渡す
                $redirect->with('errorMessages', $errors);
            }

            return $redirect;
        } else {
            // 単一登録モード（後方互換性）
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
    }

    /**
     * 予約枠編集フォームを表示
     */
    public function edit(EventTimeslot $timeslot)
    {
        $timeslot->load('event', 'venue');
        $timeslot->event->load('venues');
        $venues = $timeslot->event->venues;
        
        // 予約を取得（会場IDと時間が一致するもののみ）
        $reservationQuery = $timeslot->event->reservations()
            ->where('reservation_datetime', $timeslot->start_at->format('Y-m-d H:i:s'));
        
        // 予約枠に会場IDが設定されている場合、同じ会場の予約のみ取得
        if ($timeslot->venue_id) {
            $reservationQuery->where('venue_id', $timeslot->venue_id);
        } else {
            // 予約枠に会場IDが設定されていない場合、venue_idがnullの予約のみ取得
            $reservationQuery->whereNull('venue_id');
        }
        
        $reservationCount = $reservationQuery->count();
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
        // 予約を取得（会場IDと時間が一致するもののみ）
        $reservationQuery = $timeslot->event->reservations()
            ->where('reservation_datetime', $timeslot->start_at->format('Y-m-d H:i:s'));
        
        // 予約枠に会場IDが設定されている場合、同じ会場の予約のみ取得
        if ($timeslot->venue_id) {
            $reservationQuery->where('venue_id', $timeslot->venue_id);
        } else {
            // 予約枠に会場IDが設定されていない場合、venue_idがnullの予約のみ取得
            $reservationQuery->whereNull('venue_id');
        }
        
        $reservationCount = $reservationQuery->count();

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
        // 予約を取得（会場IDと時間が一致するもののみ）
        $reservationQuery = $timeslot->event->reservations()
            ->where('reservation_datetime', $timeslot->start_at->format('Y-m-d H:i:s'));
        
        // 予約枠に会場IDが設定されている場合、同じ会場の予約のみ取得
        if ($timeslot->venue_id) {
            $reservationQuery->where('venue_id', $timeslot->venue_id);
        } else {
            // 予約枠に会場IDが設定されていない場合、venue_idがnullの予約のみ取得
            $reservationQuery->whereNull('venue_id');
        }
        
        $reservationCount = $reservationQuery->count();

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
            // 予約を取得（会場IDと時間が一致するもののみ）
            $reservationQuery = $timeslot->event->reservations()
                ->where('reservation_datetime', $timeslot->start_at->format('Y-m-d H:i:s'));
            
            // 予約枠に会場IDが設定されている場合、同じ会場の予約のみ取得
            if ($timeslot->venue_id) {
                $reservationQuery->where('venue_id', $timeslot->venue_id);
            } else {
                // 予約枠に会場IDが設定されていない場合、venue_idがnullの予約のみ取得
                $reservationQuery->whereNull('venue_id');
            }
            
            $reservationCount = $reservationQuery->count();

            if ($newCapacity < $reservationCount) {
                return response()->json([
                    'success' => false,
                    'message' => "既存の予約数（{$reservationCount}件）を下回ることはできません。",
                ], 400);
            }
        }

        $timeslot->update(['capacity' => $newCapacity]);

        // 更新後の情報を返す
        // 予約を取得（会場IDと時間が一致するもののみ）
        $reservationQuery = $timeslot->event->reservations()
            ->where('reservation_datetime', $timeslot->start_at->format('Y-m-d H:i:s'));
        
        // 予約枠に会場IDが設定されている場合、同じ会場の予約のみ取得
        if ($timeslot->venue_id) {
            $reservationQuery->where('venue_id', $timeslot->venue_id);
        } else {
            // 予約枠に会場IDが設定されていない場合、venue_idがnullの予約のみ取得
            $reservationQuery->whereNull('venue_id');
        }
        
        $reservationCount = $reservationQuery->count();

        return response()->json([
            'success' => true,
            'message' => '定員を更新しました。',
            'capacity' => $newCapacity,
            'reserved' => $reservationCount,
            'remaining' => max(0, $newCapacity - $reservationCount),
        ]);
    }
}

