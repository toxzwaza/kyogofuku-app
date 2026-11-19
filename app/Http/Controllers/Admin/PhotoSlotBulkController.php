<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\PhotoSlot;
use App\Models\PhotoStudio;
use App\Services\PhotoSlot\PhotoStudioHours;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * 前撮り枠をまとめて作る（条件 → プレビュー → 保存）。
 * 空き枠なので、カレンダーの予定は作らない。
 */
class PhotoSlotBulkController extends Controller
{
    private const MAX_DAYS = 93;
    private const MAX_SEATS = 3000;

    public function __construct(private PhotoStudioHours $hours)
    {
    }

    public function create(Request $request)
    {
        $studios = PhotoStudio::query()
            ->visibleToUser($request->user())
            ->active()
            ->with('weekdayHours')
            ->orderBy('name')
            ->get()
            ->map(fn (PhotoStudio $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'default_capacity' => (int) $s->default_capacity,
                'has_hours' => $s->hasHours(),
                'summary' => $this->hours->summary($s),
            ])
            ->values();

        return Inertia::render('Admin/PhotoSlot/Create', [
            'studios' => $studios,
            'maxDays' => self::MAX_DAYS,
            'maxSeats' => self::MAX_SEATS,
            'maxCapacity' => PhotoStudio::MAX_CAPACITY,
            'initialStudioId' => $request->integer('studio') ?: null,
        ]);
    }

    /**
     * 日ごとの作成案を返す（DB は変えない）
     */
    public function preview(Request $request)
    {
        [$studio, $start, $end, $excludeWeekdays, $excludeDates] = $this->validatePlanInput($request);

        return response()->json([
            'days' => $this->buildPlan($studio, $start, $end, $excludeWeekdays, $excludeDates),
            'default_capacity' => (int) $studio->default_capacity,
        ]);
    }

    public function store(Request $request)
    {
        [$studio, $start, $end] = $this->validatePlanInput($request);
        $validated = $request->validate([
            'days' => 'required|array|min:1',
            'days.*.date' => 'required|date_format:Y-m-d',
            'days.*.slots' => 'required|array|min:1',
            'days.*.slots.*.time' => 'required|date_format:H:i',
            'days.*.slots.*.capacity' => 'required|integer|min:1|max:'.PhotoStudio::MAX_CAPACITY,
        ], [
            'days.required' => '作る日が選ばれていません。',
            'days.*.slots.required' => '時刻が1つもない日があります。',
        ]);

        $today = Carbon::today();
        $plan = [];
        foreach ($validated['days'] as $day) {
            $date = Carbon::parse($day['date']);
            if ($date->lt($start) || $date->gt($end)) {
                throw ValidationException::withMessages(['days' => $day['date'].' は期間の外です。']);
            }
            if ($date->lt($today)) {
                throw ValidationException::withMessages(['days' => $day['date'].' は過去の日です。']);
            }
            foreach ($day['slots'] as $slot) {
                if (! PhotoStudioHours::isOnStep($slot['time'])) {
                    throw ValidationException::withMessages(['days' => $slot['time'].' は30分刻みではありません。']);
                }
                // 同じ日・時刻が重なって送られたら、定員の大きいほうを使う
                $key = $day['date'].' '.$slot['time'];
                $plan[$key] = max($plan[$key] ?? 0, (int) $slot['capacity']);
            }
        }

        // もう枠がある時刻は飛ばす（上書きしない）
        $existing = $this->existingTimes($studio, $start, $end);
        $rows = [];
        $now = now();
        foreach ($plan as $key => $capacity) {
            [$date, $time] = explode(' ', $key);
            if (isset($existing[$date][$time])) {
                continue;
            }
            for ($seat = 1; $seat <= $capacity; $seat++) {
                $rows[] = [
                    'photo_studio_id' => $studio->id,
                    'shoot_date' => $date,
                    'shoot_time' => $time.':00',
                    'seat_no' => $seat,
                    'details_undecided' => false,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if (! $rows) {
            throw ValidationException::withMessages(['days' => '作れる枠がありません（すべて作成済みです）。']);
        }
        if (count($rows) > self::MAX_SEATS) {
            throw ValidationException::withMessages(['days' => '一度に作れるのは'.self::MAX_SEATS.'席までです（今回 '.count($rows).'席）。期間を分けてください。']);
        }

        try {
            DB::transaction(function () use ($rows) {
                foreach (array_chunk($rows, 500) as $chunk) {
                    PhotoSlot::insert($chunk);
                }
            });
        } catch (QueryException $e) {
            // 同じ時刻をほかの人が同時に作った（同じ席は1つだけの決まりに当たった）。全部取り消しになっている
            if (($e->errorInfo[1] ?? null) === 1062) {
                throw ValidationException::withMessages(['days' => 'ほかの人が同じ時刻の枠を作りました。もう一度プレビューしてから保存してください。']);
            }
            throw $e;
        }

        $days = collect($rows)->pluck('shoot_date')->unique()->count();
        $times = collect($rows)->map(fn ($r) => $r['shoot_date'].$r['shoot_time'])->unique()->count();
        $this->log($request, $studio, $days, $times, count($rows), $start, $end);

        return redirect()->route('admin.photo-slots.index')
            ->with('success', "{$studio->name} に {$days}日分・{$times}枠（".count($rows).'席）を作りました。');
    }

    /**
     * @return array{0: PhotoStudio, 1: Carbon, 2: Carbon, 3: int[], 4: string[]}
     */
    private function validatePlanInput(Request $request): array
    {
        $validated = $request->validate([
            'photo_studio_id' => 'required|integer',
            'start_date' => 'required|date_format:Y-m-d',
            'end_date' => 'required|date_format:Y-m-d|after_or_equal:start_date',
            'exclude_weekdays' => 'nullable|array',
            'exclude_weekdays.*' => 'integer|between:0,6',
            'exclude_dates' => 'nullable|array',
            'exclude_dates.*' => 'date_format:Y-m-d',
        ], [
            'photo_studio_id.required' => 'スタジオを選んでください。',
            'end_date.after_or_equal' => '終了日は開始日以降にしてください。',
        ]);

        $studio = PhotoStudio::with('weekdayHours')->find($validated['photo_studio_id']);
        if (! $studio || ! $studio->isVisibleTo($request->user())) {
            throw ValidationException::withMessages(['photo_studio_id' => 'このスタジオは選べません。']);
        }
        if (! $studio->is_active) {
            throw ValidationException::withMessages(['photo_studio_id' => 'このスタジオは無効です。']);
        }

        $start = Carbon::parse($validated['start_date'])->startOfDay();
        $end = Carbon::parse($validated['end_date'])->startOfDay();
        if ($start->diffInDays($end) + 1 > self::MAX_DAYS) {
            throw ValidationException::withMessages(['end_date' => '期間は'.self::MAX_DAYS.'日以内にしてください。']);
        }

        return [
            $studio,
            $start,
            $end,
            array_map('intval', $validated['exclude_weekdays'] ?? []),
            $validated['exclude_dates'] ?? [],
        ];
    }

    /**
     * 日ごとの案：status は create（作る）／existing（全部作成済み）／past（過去）／excluded（除外）／no_hours（営業時間なし）
     */
    private function buildPlan(PhotoStudio $studio, Carbon $start, Carbon $end, array $excludeWeekdays, array $excludeDates): array
    {
        $existing = $this->existingTimes($studio, $start, $end);
        $today = Carbon::today();
        $days = [];

        for ($d = $start->copy(); $d->lte($end); $d->addDay()) {
            $date = $d->toDateString();
            $hours = $this->hours->hoursFor($studio, $d);
            $times = $hours ? PhotoStudioHours::timesBetween($hours['start'], $hours['end']) : [];
            $existingTimes = array_keys($existing[$date] ?? []);
            sort($existingTimes);
            $newTimes = array_values(array_diff($times, $existingTimes));

            if ($d->lt($today)) {
                $status = 'past';
            } elseif (in_array($d->dayOfWeek, $excludeWeekdays, true) || in_array($date, $excludeDates, true)) {
                $status = 'excluded';
            } elseif (! $hours) {
                $status = 'no_hours';
            } elseif (! $newTimes) {
                $status = 'existing';
            } else {
                $status = 'create';
            }

            $days[] = [
                'date' => $date,
                'weekday' => $d->dayOfWeek,
                'hours' => $hours,
                'times' => $newTimes,
                'existing_times' => $existingTimes,
                'existing_seats' => $existing[$date] ?? (object) [],
                'status' => $status,
            ];
        }

        return $days;
    }

    /**
     * 期間の中のもうある枠：[日付 => [時刻 => 席数]]
     */
    private function existingTimes(PhotoStudio $studio, Carbon $start, Carbon $end): array
    {
        $out = [];
        PhotoSlot::query()
            ->where('photo_studio_id', $studio->id)
            ->whereBetween('shoot_date', [$start->toDateString(), $end->toDateString()])
            ->whereNotNull('shoot_time')
            ->selectRaw('shoot_date, shoot_time, COUNT(*) as seats')
            ->groupBy('shoot_date', 'shoot_time')
            ->get()
            ->each(function ($r) use (&$out) {
                $out[Carbon::parse($r->shoot_date)->toDateString()][substr($r->shoot_time, 0, 5)] = (int) $r->seats;
            });

        return $out;
    }

    private function log(Request $request, PhotoStudio $studio, int $days, int $times, int $seats, Carbon $start, Carbon $end): void
    {
        try {
            $user = $request->user();
            ActivityLog::create([
                'user_id' => $user->id,
                'shop_id' => $user->shops()->first()?->id,
                'action_type' => 'create',
                'resource_type' => 'PhotoSlot',
                'resource_id' => null,
                'route_name' => 'admin.photo-slots.bulk.store',
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'description' => "前撮り枠をまとめて作成：{$studio->name}（{$start->toDateString()}〜{$end->toDateString()}）{$days}日分・{$times}枠・{$seats}席",
                'old_values' => null,
                'new_values' => [
                    'photo_studio_id' => $studio->id,
                    'start_date' => $start->toDateString(),
                    'end_date' => $end->toDateString(),
                    'days' => $days,
                    'times' => $times,
                    'seats' => $seats,
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            Log::error('前撮り枠の一括作成の記録に失敗', ['error' => $e->getMessage()]);
        }
    }
}
