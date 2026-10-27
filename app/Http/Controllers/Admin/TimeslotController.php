<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventTimeslot;
use App\Models\TimeslotTemplate;
use App\Models\Venue;
use App\Services\TimeslotOverlapService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Http\Controllers\Concerns\ResolvesUiView;
use Inertia\Inertia;

class TimeslotController extends Controller
{
    use ResolvesUiView;

    public function __construct(private TimeslotOverlapService $overlap)
    {
    }

    /**
     * 予約枠一覧を表示
     */
    public function index(Request $request, Event $event)
    {
        $view = $this->viewFor('Admin/Timeslot/Index');
        // 新画面は既定で今日以降のみ（旧画面は切替UIが無いので従来どおり全件）
        $includePast = $request->boolean('past') || str_ends_with($view, 'Legacy');
        $today = now()->startOfDay();

        $query = EventTimeslot::where('event_id', $event->id)
            ->with('venue:id,name')
            ->orderBy('start_at', 'asc');
        if (! $includePast) {
            $query->where('start_at', '>=', $today);
        }

        $slots = $query->get();
        $timeslots = $this->slotRows($event, $slots);

        $event->load('venues:id,name');

        return Inertia::render($view, [
            'event' => $event,
            'timeslots' => $timeslots,
            'venues' => $event->venues->map->only('id', 'name')->values(),
            'includePast' => $includePast,
            'pastCount' => $includePast ? 0 : EventTimeslot::where('event_id', $event->id)->where('start_at', '<', $today)->count(),
            'overlaps' => str_ends_with($view, 'Legacy') ? [] : $this->overlapsFor($event, $slots),
        ]);
    }

    /**
     * 一覧で選んだ枠をまとめて受付停止／再開・定員増減する（JSON）
     */
    public function bulkUpdate(Request $request, Event $event)
    {
        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
            'action' => 'required|in:deactivate,activate,capacity',
            'amount' => 'required_if:action,capacity|integer|between:-999,999|not_in:0',
        ], [
            'amount.not_in' => '増減する数を指定してください。',
        ]);

        $result = DB::transaction(function () use ($event, $validated) {
            // 実行時点の値で判定し直す（画面を開いた後に予約が入っていても定員が予約数を下回らないように）
            $slots = EventTimeslot::where('event_id', $event->id)
                ->whereIn('id', $validated['ids'])
                ->lockForUpdate()
                ->get();
            $reservedCounts = $this->reservedCounts($event);

            $updated = [];
            $skipped = [];
            foreach ($slots as $slot) {
                $reserved = $reservedCounts[$this->slotKey($slot)] ?? 0;
                if ($validated['action'] === 'capacity') {
                    $newCapacity = $slot->capacity + (int) $validated['amount'];
                    if ($newCapacity < max(1, $reserved)) {
                        $skipped[] = ['id' => $slot->id, 'label' => $slot->start_at->format('n/j H:i'), 'reason' => $reserved > 0 ? "予約数（{$reserved}件）を下回るため変更しません" : '定員は1以上です'];
                        continue;
                    }
                    $slot->capacity = $newCapacity;
                } else {
                    $slot->is_active = $validated['action'] === 'activate';
                }
                if ($slot->isDirty()) {
                    $slot->save();
                    $updated[] = $slot->id;
                }
            }

            return [$updated, $skipped];
        });
        [$updated, $skipped] = $result;

        $label = ['deactivate' => '受付を停止', 'activate' => '受付を再開', 'capacity' => '定員を変更'][$validated['action']];
        $message = count($updated)."件の枠の{$label}しました。".($skipped ? '（'.count($skipped).'件は変更なし）' : '');

        $slots = EventTimeslot::where('event_id', $event->id)->whereIn('id', $validated['ids'])->with('venue:id,name')->get();

        return response()->json([
            'success' => true,
            'message' => $message,
            'timeslots' => $this->slotRows($event, $slots),
            'skipped' => $skipped,
        ]);
    }

    /**
     * 有効な予約数: "会場|予約日時" => 件数（枠ごとにCOUNTしないよう1回で集計）
     */
    private function reservedCounts(Event $event)
    {
        return $event->reservations()
            ->where('cancel_flg', false)
            ->whereNotNull('reservation_datetime')
            ->selectRaw('venue_id, reservation_datetime, COUNT(*) as cnt')
            ->groupBy('venue_id', 'reservation_datetime')
            ->get()
            ->mapWithKeys(fn ($r) => [($r->venue_id ?? '').'|'.$r->reservation_datetime => (int) $r->cnt]);
    }

    private function slotKey(EventTimeslot $t): string
    {
        return ($t->venue_id ?? '').'|'.$t->start_at->format('Y-m-d H:i:s');
    }

    /**
     * 一覧用の枠データ。モデルの remaining_capacity アクセサは枠ごとにCOUNTを投げるため、配列にして返す
     */
    private function slotRows(Event $event, $slots)
    {
        $reservedCounts = $this->reservedCounts($event);

        return $slots->map(function (EventTimeslot $t) use ($reservedCounts) {
            $reserved = $reservedCounts[$this->slotKey($t)] ?? 0;

            return [
                'id' => $t->id,
                'event_id' => $t->event_id,
                'venue_id' => $t->venue_id,
                'venue' => $t->venue ? ['id' => $t->venue->id, 'name' => $t->venue->name] : null,
                'start_at' => $t->start_at->toJSON(),
                // 日付・時刻の表示と予約一覧の絞り込みに使う（店舗の時刻のまま）
                'start_local' => $t->start_at->format('Y-m-d H:i:s'),
                'capacity' => $t->capacity,
                'is_active' => $t->is_active,
                'reserved_count' => $reserved,
                'remaining_capacity' => max(0, $t->capacity - $reserved),
            ];
        })->values();
    }

    /**
     * 表示する枠の日付で、同じ店舗の別イベントと重なる会場: date => [会場ID or "none" => 重なり一覧]
     */
    private function overlapsFor(Event $event, $slots): array
    {
        if ($slots->isEmpty()) {
            return [];
        }
        $event->loadMissing('venues:id');
        $eventVenueIds = $event->venues->pluck('id')->all();
        $others = $this->overlap->otherEventSlots(
            $event,
            $slots->min('start_at')->format('Y-m-d'),
            $slots->max('start_at')->format('Y-m-d'),
        );

        $result = [];
        foreach ($slots->groupBy(fn ($t) => $t->start_at->format('Y-m-d')) as $date => $daySlots) {
            if (empty($others[$date])) {
                continue;
            }
            foreach ($daySlots->pluck('venue_id')->unique() as $venueId) {
                $conflicts = $this->overlap->conflictsFor($others[$date], $venueId, $eventVenueIds);
                if ($conflicts) {
                    $result[$date][$venueId ?? 'none'] = $conflicts;
                }
            }
        }

        return $result;
    }

    /**
     * 一覧の表示条件（会場・状態・日付など）だけを取り出す。編集画面から一覧へ戻るときに引き継ぐ
     */
    private function indexQuery(?string $query): array
    {
        parse_str((string) $query, $params);

        return collect($params)
            ->only(['past', 'v', 's', 'd', 'view'])
            ->filter(fn ($v) => is_string($v) && $v !== '' && preg_match('/^[\w,\-]{1,200}$/', $v))
            ->all();
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

        // テンプレートグループ一覧を取得
        $templates = TimeslotTemplate::with('slots')
            ->orderBy('name', 'asc')
            ->get();

        return Inertia::render($this->viewFor('Admin/Timeslot/Create'), [
            'event' => $event,
            'venues' => $venues,
            'duplicateTimeslot' => $duplicateTimeslot,
            'existingTimeslots' => $existingTimeslots,
            'templates' => $templates,
        ]);
    }

    /**
     * 予約枠を保存（複数件一括登録対応）
     */
    public function store(Request $request, Event $event)
    {
        $event->load('venues');
        
        // テンプレートからの一括作成
        if ($request->has('template_id') && $request->has('date') && $request->has('venue_id')) {
            $template = TimeslotTemplate::with('slots')->find($request->template_id);
            
            if (!$template) {
                return redirect()->back()
                    ->withErrors(['template_id' => 'テンプレートが見つかりません。']);
            }

            $date = $request->date;
            $venueId = $request->venue_id ?: null;
            $isActive = $request->has('is_active') ? $request->is_active : true;

            $timeslots = [];
            foreach ($template->slots as $slot) {
                $startAt = $date . ' ' . str_pad($slot->hour, 2, '0', STR_PAD_LEFT) . ':' . str_pad($slot->minute, 2, '0', STR_PAD_LEFT) . ':00';
                $timeslots[] = [
                    'venue_id' => $venueId,
                    'start_at' => $startAt,
                    'capacity' => $slot->capacity,
                    'is_active' => $isActive,
                ];
            }

            $request->merge(['timeslots' => $timeslots]);
        }
        
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
    public function edit(Request $request, EventTimeslot $timeslot)
    {
        $timeslot->load('event', 'venue');
        $timeslot->event->load('venues');
        $venues = $timeslot->event->venues;
        
        // 予約を取得（会場IDと時間が一致するもののみ。キャンセル済みは除外）
        $reservationQuery = $timeslot->event->reservations()
            ->where('cancel_flg', false)
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

        $returnQuery = $this->indexQuery($request->query('return'));

        return Inertia::render($this->viewFor('Admin/Timeslot/Edit'), [
            'timeslot' => $timeslot,
            'venues' => $venues,
            'returnQuery' => http_build_query($returnQuery),
            'backUrl' => route('admin.events.timeslots.index', ['event' => $timeslot->event_id] + $returnQuery),
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
        // 予約を取得（会場IDと時間が一致するもののみ。キャンセル済みは除外）
        $reservationQuery = $timeslot->event->reservations()
            ->where('cancel_flg', false)
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

        return redirect()->route('admin.events.timeslots.index', ['event' => $timeslot->event_id] + $this->indexQuery($request->input('return_query')))
            ->with('success', '予約枠を更新しました。');
    }

    /**
     * 予約枠を削除
     */
    public function destroy(Request $request, EventTimeslot $timeslot)
    {
        $eventId = $timeslot->event_id;
        
        // 既存の予約がある場合は削除不可（キャンセルされていない予約のみカウント）
        // 予約を取得（会場IDと時間が一致するもののみ。キャンセル済みは除外）
        $reservationQuery = $timeslot->event->reservations()
            ->where('cancel_flg', false)
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
            $message = "予約が存在するため削除できません。（予約数: {$reservationCount}件）";
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $message], 422);
            }
            return redirect()->back()
                ->withErrors(['error' => $message]);
        }

        $timeslot->delete();

        if ($request->wantsJson()) {
            return response()->json(['success' => true, 'message' => '予約枠を削除しました。']);
        }
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
            // 予約を取得（会場IDと時間が一致するもののみ。キャンセル済みは除外）
            $reservationQuery = $timeslot->event->reservations()
                ->where('cancel_flg', false)
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
        // 予約を取得（会場IDと時間が一致するもののみ。キャンセル済みは除外）
        $reservationQuery = $timeslot->event->reservations()
            ->where('cancel_flg', false)
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

