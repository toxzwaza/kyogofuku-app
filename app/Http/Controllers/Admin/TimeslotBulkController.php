<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventTimeslot;
use App\Models\TimeslotTemplate;
use App\Services\TimeslotOverlapService;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * 予約枠の期間一括作成（期間×会場×時間帯をプレビューしてから登録）
 *
 * 同じ店舗の別予約イベントに枠がある日は「重なり」として警告し、既定では作成対象から外す。
 * 判定の内容は TimeslotOverlapService を参照。
 */
class TimeslotBulkController extends Controller
{
    private const MAX_DAYS = 93;
    private const MAX_SLOTS = 3000;

    public function __construct(private TimeslotOverlapService $overlap)
    {
    }

    public function create(Event $event)
    {
        $event->load('venues:id,name', 'shops:id,name');

        return Inertia::render('Admin/Timeslot/Bulk', [
            'event' => $event->only('id', 'title', 'form_type'),
            'venues' => $event->venues->map->only('id', 'name')->values(),
            'shops' => $event->shops->map->only('id', 'name')->values(),
            'templates' => TimeslotTemplate::with('slots')->orderBy('name')->get()->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->name,
                'slots' => $t->slots->sortBy(fn ($s) => $s->hour * 60 + $s->minute)->map(fn ($s) => [
                    'time' => sprintf('%02d:%02d', $s->hour, $s->minute),
                    'capacity' => $s->capacity,
                ])->values(),
            ]),
        ]);
    }

    /**
     * 会場×日付ごとの作成予定を返す（DBは変更しない）
     */
    public function preview(Request $request, Event $event)
    {
        $input = $this->validatePlanInput($request, $event);

        return response()->json($this->buildPlan($event, $input));
    }

    /**
     * 期間内で同じ店舗の別予約イベントに有効な枠がある日（除外する日の候補として表示する）
     */
    public function overlaps(Request $request, Event $event)
    {
        $validated = $request->validate([
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
        ]);
        $start = CarbonImmutable::parse($validated['start_date']);
        $end = CarbonImmutable::parse($validated['end_date']);
        if ($start->diffInDays($end) + 1 > self::MAX_DAYS) {
            return response()->json(['dates' => []]);
        }

        $others = $this->overlap->otherEventSlots($event, $start->toDateString(), $end->toDateString());
        ksort($others);

        return response()->json([
            'dates' => collect($others)->map(fn ($events, $date) => [
                'date' => $date,
                'events' => array_values(array_map(fn ($e) => [
                    'event_id' => $e['event_id'],
                    'title' => $e['title'],
                    'venue_names' => $e['venue_names'],
                ], $events)),
            ])->values(),
        ]);
    }

    /**
     * プレビューで選ばれた会場×日付に枠を作成する
     */
    public function store(Request $request, Event $event)
    {
        $input = $this->validatePlanInput($request, $event);
        $request->validate([
            'cells' => 'required|array|min:1',
            'cells.*.date' => 'required|date_format:Y-m-d',
            'cells.*.venue_id' => 'nullable|integer',
        ], ['cells.required' => '作成する日が選ばれていません。']);

        $plan = $this->buildPlan($event, $input);
        $planCells = collect($plan['cells'])->keyBy(fn ($c) => $c['date'].'|'.($c['venue_id'] ?? ''));

        $rows = [];
        $seen = [];
        $now = now();
        foreach ($request->input('cells') as $cell) {
            $key = $cell['date'].'|'.($cell['venue_id'] ?? '');
            // 同じ日・会場が重複して送られても1回分だけ作る
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $planned = $planCells->get($key);
            // プレビューで選べるセル（作成・重なり）だけ作る。期間外・過去日・除外日・会場違いは無視
            if (! $planned || ! in_array($planned['status'], ['create', 'conflict'], true)) {
                continue;
            }
            foreach ($planned['new_times'] as $slot) {
                $rows[] = [
                    'event_id' => $event->id,
                    'venue_id' => $planned['venue_id'],
                    'start_at' => $cell['date'].' '.$slot['time'].':00',
                    'capacity' => $slot['capacity'],
                    'is_active' => $input['is_active'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (! $rows) {
            return back()->withErrors(['cells' => '作成できる枠がありません（すべて既存または対象外です）。']);
        }
        if (count($rows) > self::MAX_SLOTS) {
            return back()->withErrors(['cells' => '一度に作成できる枠は'.self::MAX_SLOTS.'件までです。期間を分けてください。']);
        }

        DB::transaction(function () use ($rows) {
            foreach (array_chunk($rows, 500) as $chunk) {
                EventTimeslot::insert($chunk);
            }
        });

        $days = collect($rows)->map(fn ($r) => substr($r['start_at'], 0, 10).'|'.$r['venue_id'])->unique()->count();

        return redirect()->route('admin.events.timeslots.index', $event->id)
            ->with('success', count($rows)."件の予約枠を追加しました（{$days}日・会場分）。");
    }

    private function validatePlanInput(Request $request, Event $event): array
    {
        $validated = $request->validate([
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'venue_ids' => 'nullable|array',
            'venue_ids.*' => 'integer',
            'exclude_weekdays' => 'nullable|array',
            'exclude_weekdays.*' => 'integer|between:0,6',
            'exclude_dates' => 'nullable|array',
            'exclude_dates.*' => 'date_format:Y-m-d',
            'times' => 'required|array|min:1|max:48',
            'times.*.time' => ['required', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'times.*.capacity' => 'required|integer|min:1|max:999',
            'is_active' => 'boolean',
        ], [
            'end_date.after_or_equal' => '終了日は開始日以降にしてください。',
            'times.required' => '時間帯を1つ以上指定してください。',
            'times.*.time.regex' => '時刻は HH:MM 形式で指定してください。',
        ]);

        $start = CarbonImmutable::parse($validated['start_date']);
        $end = CarbonImmutable::parse($validated['end_date']);
        if ($start->diffInDays($end) + 1 > self::MAX_DAYS) {
            throw ValidationException::withMessages(['end_date' => '期間は'.self::MAX_DAYS.'日以内にしてください。']);
        }

        $event->loadMissing('venues:id,name');
        $eventVenueIds = $event->venues->pluck('id')->all();
        $venueIds = array_values(array_intersect(array_map('intval', $validated['venue_ids'] ?? []), $eventVenueIds));
        if (! $eventVenueIds) {
            $venueIds = [null]; // 会場なしのイベント
        } elseif (! $venueIds) {
            throw ValidationException::withMessages(['venue_ids' => '会場を1つ以上選んでください。']);
        }

        // 同じ時刻は後勝ちにせず最初の指定を使う
        $times = collect($validated['times'])->unique('time')->sortBy('time')->values()->all();

        return [
            'start' => $start,
            'end' => $end,
            'venue_ids' => $venueIds,
            'exclude_weekdays' => array_map('intval', $validated['exclude_weekdays'] ?? []),
            'exclude_dates' => $validated['exclude_dates'] ?? [],
            'times' => $times,
            'is_active' => $validated['is_active'] ?? true,
        ];
    }

    private function buildPlan(Event $event, array $input): array
    {
        $event->loadMissing('venues:id,name');
        $eventVenueIds = $event->venues->pluck('id')->all();
        $venueNames = $event->venues->pluck('name', 'id');
        $startStr = $input['start']->toDateString();
        $endStr = $input['end']->toDateString();
        $today = now()->toDateString();

        // このイベントの既存枠: "date|venue" => [HH:MM => true]
        $existing = [];
        EventTimeslot::where('event_id', $event->id)
            ->whereBetween('start_at', [$startStr.' 00:00:00', $endStr.' 23:59:59'])
            ->get(['venue_id', 'start_at'])
            ->each(function ($t) use (&$existing) {
                $existing[$t->start_at->format('Y-m-d').'|'.($t->venue_id ?? '')][$t->start_at->format('H:i')] = true;
            });

        $others = $this->overlap->otherEventSlots($event, $startStr, $endStr);

        $cells = [];
        foreach (CarbonPeriod::create($input['start'], $input['end']) as $day) {
            $date = $day->toDateString();
            foreach ($input['venue_ids'] as $venueId) {
                $have = $existing[$date.'|'.($venueId ?? '')] ?? [];
                $newTimes = array_values(array_filter($input['times'], fn ($t) => ! isset($have[$t['time']])));
                $conflicts = $this->overlap->conflictsFor($others[$date] ?? [], $venueId, $eventVenueIds);

                $status = match (true) {
                    $date < $today => 'past',
                    in_array($day->dayOfWeek, $input['exclude_weekdays'], true) => 'weekday',
                    in_array($date, $input['exclude_dates'], true) => 'excluded',
                    ! $newTimes => 'existing',
                    (bool) $conflicts => 'conflict',
                    default => 'create',
                };

                $cells[] = [
                    'date' => $date,
                    'venue_id' => $venueId,
                    'status' => $status,
                    'existing_count' => count($have),
                    'new_times' => $newTimes,
                    'conflicts' => $conflicts,
                ];
            }
        }

        return [
            'venues' => collect($input['venue_ids'])->map(fn ($id) => ['id' => $id, 'name' => $id ? $venueNames[$id] : '会場なし'])->values(),
            'cells' => $cells,
        ];
    }
}
