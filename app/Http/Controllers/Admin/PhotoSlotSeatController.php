<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PhotoSlot;
use App\Models\PhotoStudio;
use App\Services\PhotoSlot\PhotoStudioHours;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 前撮り枠の席の管理：その時刻に1枠足す・定員を変える・空き枠を消す。
 * 権限機能を作るときに、ここをまとめて「前撮りの管理」の権限にする（docs/20261119_権限機能の整備_権限を設ける場所.md）。
 */
class PhotoSlotSeatController extends Controller
{
    /**
     * その時刻に1枠足す（枠がない時刻・営業時間の外でも足せる）
     */
    public function store(Request $request)
    {
        [$studio, $date, $time] = $this->validateTarget($request);

        $capacity = DB::transaction(function () use ($studio, $date, $time) {
            $seats = $this->lockSeats($studio, $date, $time);
            if ($seats->count() >= PhotoStudio::MAX_CAPACITY) {
                throw ValidationException::withMessages(['capacity' => '1枠の定員は'.PhotoStudio::MAX_CAPACITY.'人までです。']);
            }
            $this->createSeats($studio, $date, $time, (int) $seats->max('seat_no'), 1);

            return $seats->count() + 1;
        });

        return $this->done($request, "{$date} {$time} に1枠足しました（定員 {$capacity}人）。");
    }

    /**
     * その時刻の定員を変える。減らすときは、番号の大きい空き席から消す（予約の入っている席は消さない）
     */
    public function capacity(Request $request)
    {
        [$studio, $date, $time] = $this->validateTarget($request);
        $capacity = (int) $request->validate([
            'capacity' => 'required|integer|min:1|max:'.PhotoStudio::MAX_CAPACITY,
        ], [
            'capacity.min' => '定員は1人以上にしてください（枠をなくすときは「空き枠を消す」を使ってください）。',
            'capacity.max' => '1枠の定員は'.PhotoStudio::MAX_CAPACITY.'人までです。',
        ])['capacity'];

        DB::transaction(function () use ($studio, $date, $time, $capacity) {
            $seats = $this->lockSeats($studio, $date, $time);
            $now = $seats->count();
            if ($now === 0) {
                throw ValidationException::withMessages(['capacity' => 'この時刻には枠がありません。「1枠足す」を使ってください。']);
            }
            if ($capacity > $now) {
                $this->createSeats($studio, $date, $time, (int) $seats->max('seat_no'), $capacity - $now);
            } elseif ($capacity < $now) {
                $vacant = $seats->whereNull('customer_id')->sortByDesc('seat_no')->values();
                $remove = $now - $capacity;
                $booked = $now - $vacant->count();
                if ($vacant->count() < $remove) {
                    throw ValidationException::withMessages(['capacity' => "予約が{$booked}人入っているため、定員を{$booked}人より少なくできません。"]);
                }
                // 1件ずつ消す（PhotoSlotObserver の deleting を通す）
                $vacant->take($remove)->each(fn (PhotoSlot $s) => $s->delete());
            }
        });

        return $this->done($request, "{$date} {$time} の定員を{$capacity}人にしました。");
    }

    /**
     * 空き枠（席1つ）を消す。お客様が入っている席は消せない
     */
    public function destroy(Request $request, PhotoSlot $photoSlot)
    {
        if (! PhotoSlot::query()->visibleToUser($request->user())->whereKey($photoSlot->id)->exists()) {
            abort(404);
        }
        // 席を押さえて読み直してから消す（読んだ後に予約が入っていたら消さない）
        $deleted = DB::transaction(function () use ($photoSlot) {
            $fresh = PhotoSlot::whereKey($photoSlot->id)->lockForUpdate()->first();
            if (! $fresh) {
                return true; // もう消えている
            }
            if ($fresh->customer_id !== null) {
                return false;
            }
            $fresh->delete();

            return true;
        });
        if (! $deleted) {
            $message = '顧客が紐づいている前撮り枠は削除できません。先に「解除」を実行してください。';
            if ($request->expectsJson()) {
                return response()->json(['message' => $message, 'errors' => ['error' => [$message]]], 422);
            }

            return back()->withErrors(['error' => $message]);
        }

        return $this->done($request, '前撮り枠を削除しました。');
    }

    /**
     * @return array{0: PhotoStudio, 1: string, 2: string}
     */
    private function validateTarget(Request $request): array
    {
        $validated = $request->validate([
            'photo_studio_id' => 'required|integer',
            'shoot_date' => 'required|date_format:Y-m-d',
            'shoot_time' => 'required|date_format:H:i',
        ]);
        $studio = PhotoStudio::find($validated['photo_studio_id']);
        if (! $studio || ! $studio->isVisibleTo($request->user())) {
            throw ValidationException::withMessages(['photo_studio_id' => 'このスタジオは選べません。']);
        }
        if (! PhotoStudioHours::isOnStep($validated['shoot_time'])) {
            throw ValidationException::withMessages(['shoot_time' => '時刻は30分刻みにしてください。']);
        }
        if (Carbon::parse($validated['shoot_date'])->lt(Carbon::today())) {
            throw ValidationException::withMessages(['shoot_date' => '過去の日の枠は変えられません。']);
        }

        return [$studio, $validated['shoot_date'], $validated['shoot_time']];
    }

    private function lockSeats(PhotoStudio $studio, string $date, string $time)
    {
        return PhotoSlot::query()
            ->where('photo_studio_id', $studio->id)
            ->whereDate('shoot_date', $date)
            ->where('shoot_time', $time.':00')
            ->where('details_undecided', false)
            ->orderBy('seat_no')
            ->lockForUpdate()
            ->get();
    }

    private function createSeats(PhotoStudio $studio, string $date, string $time, int $maxSeatNo, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            PhotoSlot::create([
                'photo_studio_id' => $studio->id,
                'shoot_date' => $date,
                'shoot_time' => $time.':00',
                'seat_no' => $maxSeatNo + $i,
                'details_undecided' => false,
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
