<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeijinPrepSlot;
use App\Models\SeijinPrepVenue;
use App\Services\PhotoSlot\PhotoStudioHours;
use App\Services\SeijinPrep\SeijinPrepService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * お支度の枠の席（レーン）：その時刻に1レーン足す・定員を変える・空き席を消す・1日分をまとめて作る。
 * 権限は今回設けない。権限機能の整備で「成人式お支度の管理」権限を付ける場所
 * （docs/20261119_権限機能の整備_権限を設ける場所.md）。
 */
class SeijinPrepSeatController extends Controller
{
    public function __construct(private SeijinPrepService $service)
    {
    }

    public function store(Request $request)
    {
        [$venue, $date, $time] = $this->validateTarget($request);

        $capacity = DB::transaction(function () use ($venue, $date, $time) {
            $seats = $this->lockSeats($venue, $date, $time);
            if ($seats->count() >= SeijinPrepVenue::MAX_CAPACITY) {
                throw ValidationException::withMessages(['capacity' => '1枠の定員は'.SeijinPrepVenue::MAX_CAPACITY.'人までです。']);
            }
            $this->createSeats($venue, $date, $time, (int) $seats->max('seat_no'), 1);

            return $seats->count() + 1;
        });

        return $this->done($request, "{$date} {$time} に1レーン足しました（定員 {$capacity}人）。");
    }

    public function capacity(Request $request)
    {
        [$venue, $date, $time] = $this->validateTarget($request);
        $capacity = (int) $request->validate([
            'capacity' => 'required|integer|min:1|max:'.SeijinPrepVenue::MAX_CAPACITY,
        ], [
            'capacity.min' => '定員は1人以上にしてください（枠をなくすときは空き席を消してください）。',
            'capacity.max' => '1枠の定員は'.SeijinPrepVenue::MAX_CAPACITY.'人までです。',
        ])['capacity'];

        DB::transaction(function () use ($venue, $date, $time, $capacity) {
            $seats = $this->lockSeats($venue, $date, $time);
            $now = $seats->count();
            if ($now === 0) {
                throw ValidationException::withMessages(['capacity' => 'この時刻には枠がありません。「1レーン足す」を使ってください。']);
            }
            if ($capacity > $now) {
                $this->createSeats($venue, $date, $time, (int) $seats->max('seat_no'), $capacity - $now);
            } elseif ($capacity < $now) {
                $vacant = $seats->whereNull('customer_id')->sortByDesc('seat_no')->values();
                $booked = $now - $vacant->count();
                if ($vacant->count() < $now - $capacity) {
                    throw ValidationException::withMessages(['capacity' => "登録が{$booked}人入っているため、定員を{$booked}人より少なくできません。"]);
                }
                $vacant->take($now - $capacity)->each(fn (SeijinPrepSlot $s) => $s->delete());
            }
        });

        return $this->done($request, "{$date} {$time} の定員を{$capacity}人にしました。");
    }

    public function destroy(Request $request, SeijinPrepSlot $seijinPrepSlot)
    {
        if (! $seijinPrepSlot->isSeat() || ! SeijinPrepSlot::query()->visibleToUser($request->user())->whereKey($seijinPrepSlot->id)->exists()) {
            abort(404);
        }
        $deleted = DB::transaction(function () use ($seijinPrepSlot) {
            if ($seijinPrepSlot->seijin_prep_venue_id) {
                SeijinPrepVenue::whereKey($seijinPrepSlot->seijin_prep_venue_id)->lockForUpdate()->first();
            }
            $fresh = SeijinPrepSlot::whereKey($seijinPrepSlot->id)->lockForUpdate()->first();
            if (! $fresh) {
                return true;
            }
            if ($fresh->customer_id !== null) {
                return false;
            }
            $fresh->delete();

            return true;
        });
        if (! $deleted) {
            $message = 'お客様が入っている席は消せません。先にお客様の登録を外してください。';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'errors' => ['error' => [$message]]], 422);
            }

            return back()->withErrors(['error' => $message]);
        }

        return $this->done($request, '空き席を消しました。');
    }

    /**
     * 1日分をまとめて作る：開始〜終了を場所の刻みで区切り、各時刻に定員ぶんの席を作る。もうある時刻は飛ばす。
     */
    public function bulk(Request $request)
    {
        $v = $request->validate([
            'seijin_prep_venue_id' => 'required|integer',
            'prep_date' => 'required|date_format:Y-m-d',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i',
            'capacity' => 'required|integer|min:1|max:'.SeijinPrepVenue::MAX_CAPACITY,
        ], [], [
            'prep_date' => '日', 'start_time' => '開始時刻', 'end_time' => '終了時刻', 'capacity' => '定員',
        ]);
        $venue = $this->visibleVenue($request, (int) $v['seijin_prep_venue_id']);
        if (Carbon::parse($v['prep_date'])->lt(Carbon::today())) {
            throw ValidationException::withMessages(['prep_date' => '過去の日には作れません。']);
        }
        $interval = $venue->interval();
        $from = PhotoStudioHours::toMinutes($v['start_time']);
        $to = PhotoStudioHours::toMinutes($v['end_time']);
        $errors = [];
        foreach (['start_time' => $from, 'end_time' => $to] as $f => $m) {
            if ($m % $interval !== 0) {
                $errors[$f] = "時刻は{$interval}分刻みにしてください。";
            }
        }
        if ($from > $to) {
            $errors['end_time'] = '終了時刻は開始時刻と同じか後にしてください。';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        [$made, $skipped] = DB::transaction(function () use ($venue, $v, $from, $to, $interval) {
            // 場所の行を押さえて、同じ場所のまとめて作成を1つずつ進める（同時に押されても一意制約で落ちない）
            SeijinPrepVenue::whereKey($venue->id)->lockForUpdate()->first();
            $existing = SeijinPrepSlot::query()
                ->where('seijin_prep_venue_id', $venue->id)
                ->whereDate('prep_date', $v['prep_date'])
                ->whereNotNull('prep_time')
                ->pluck('prep_time')
                ->map(fn ($t) => substr($t, 0, 5))
                ->unique()
                ->all();
            $made = 0;
            $skipped = 0;
            for ($m = $from; $m <= $to; $m += $interval) {
                $time = sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
                if (in_array($time, $existing, true)) {
                    $skipped++;

                    continue;
                }
                $this->createSeats($venue, $v['prep_date'], $time, 0, (int) $v['capacity']);
                $made++;
            }

            return [$made, $skipped];
        });

        $this->service->log($request->user(), 'create', null,
            "成人式お支度の枠をまとめて作成：{$venue->name} {$v['prep_date']} {$v['start_time']}〜{$v['end_time']}（{$made}時刻・定員{$v['capacity']}、既にあって飛ばした{$skipped}時刻）",
            null, ['場所' => $venue->name, '日' => $v['prep_date'], '作った時刻' => $made, '飛ばした時刻' => $skipped, '定員' => (int) $v['capacity']]);

        $message = "{$venue->name} {$v['prep_date']} に{$made}時刻（定員{$v['capacity']}）の枠を作りました。".($skipped ? "既にあった{$skipped}時刻は飛ばしました。" : '');

        return $this->done($request, $message);
    }

    /** @return array{0: SeijinPrepVenue, 1: string, 2: string} */
    private function validateTarget(Request $request): array
    {
        $v = $request->validate([
            'seijin_prep_venue_id' => 'required|integer',
            'prep_date' => 'required|date_format:Y-m-d',
            'prep_time' => 'required|date_format:H:i',
        ]);
        $venue = $this->visibleVenue($request, (int) $v['seijin_prep_venue_id']);
        if (! $venue->isOnStep($v['prep_time'])) {
            throw ValidationException::withMessages(['prep_time' => "時刻は{$venue->interval()}分刻みにしてください。"]);
        }
        if (Carbon::parse($v['prep_date'])->lt(Carbon::today())) {
            throw ValidationException::withMessages(['prep_date' => '過去の日の枠は変えられません。']);
        }

        return [$venue, $v['prep_date'], $v['prep_time']];
    }

    private function visibleVenue(Request $request, int $id): SeijinPrepVenue
    {
        $venue = SeijinPrepVenue::find($id);
        if (! $venue || ! $venue->isVisibleTo($request->user())) {
            throw ValidationException::withMessages(['seijin_prep_venue_id' => 'このお支度場所は選べません。']);
        }

        return $venue;
    }

    /** 場所の行を先に押さえてから席を押さえる（枠のない時刻でも、まとめて作成・ほかの「＋」と1つずつ進む） */
    private function lockSeats(SeijinPrepVenue $venue, string $date, string $time)
    {
        SeijinPrepVenue::whereKey($venue->id)->lockForUpdate()->first();

        return SeijinPrepSlot::query()
            ->where('seijin_prep_venue_id', $venue->id)
            ->whereDate('prep_date', $date)
            ->where('prep_time', $time.':00')
            ->orderBy('seat_no')
            ->lockForUpdate()
            ->get();
    }

    private function createSeats(SeijinPrepVenue $venue, string $date, string $time, int $maxSeatNo, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            SeijinPrepSlot::create([
                'seijin_prep_venue_id' => $venue->id,
                'prep_date' => $date,
                'prep_time' => $time.':00',
                'seat_no' => $maxSeatNo + $i,
            ]);
        }
    }

    private function done(Request $request, string $message)
    {
        if ($request->expectsJson()) {
            return response()->json(['success' => true, 'message' => $message]);
        }

        return back()->with('success', $message);
    }
}
