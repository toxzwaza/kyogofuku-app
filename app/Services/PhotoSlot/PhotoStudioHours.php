<?php

namespace App\Services\PhotoSlot;

use App\Models\PhotoStudio;
use Carbon\Carbon;

/**
 * 前撮りスタジオの営業時間から、その日の枠の時刻を作る。
 * 枠は30分刻みで、開始時刻から「終了時刻の30分前」まで。
 * 曜日ごとの営業時間があればそれを、なければ既定の営業時間を使う。
 */
class PhotoStudioHours
{
    public const STEP_MINUTES = 30;

    /**
     * その日に使う営業時間。営業時間が未登録なら null。
     *
     * @return array{start:string,end:string,source:string}|null source は weekday（曜日の例外）／default（既定）
     */
    public function hoursFor(PhotoStudio $studio, Carbon $date): ?array
    {
        $studio->loadMissing('weekdayHours');
        $weekday = $studio->weekdayHours->firstWhere('weekday', $date->dayOfWeek);
        if ($weekday) {
            return ['start' => self::hm($weekday->start_time), 'end' => self::hm($weekday->end_time), 'source' => 'weekday'];
        }
        if (! $studio->hasHours()) {
            return null;
        }

        return ['start' => self::hm($studio->default_start_time), 'end' => self::hm($studio->default_end_time), 'source' => 'default'];
    }

    /** その日の枠の時刻（'H:i' の配列） */
    public function timesFor(PhotoStudio $studio, Carbon $date): array
    {
        $hours = $this->hoursFor($studio, $date);

        return $hours ? self::timesBetween($hours['start'], $hours['end']) : [];
    }

    /** 開始〜終了の間の30分刻みの時刻（最後の枠は終了の30分前） */
    public static function timesBetween(string $start, string $end): array
    {
        $s = self::toMinutes($start);
        $e = self::toMinutes($end);
        $times = [];
        for ($m = $s; $m + self::STEP_MINUTES <= $e; $m += self::STEP_MINUTES) {
            $times[] = self::fromMinutes($m);
        }

        return $times;
    }

    /** 30分刻みの時刻か（'H:i' または 'H:i:s'） */
    public static function isOnStep(string $time): bool
    {
        if (! preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:00)?$/', $time, $m)) {
            return false;
        }

        return ((int) $m[2]) % self::STEP_MINUTES === 0;
    }

    /** 'H:i:s' / 'H:i' → 'H:i' */
    public static function hm(?string $time): ?string
    {
        return $time === null ? null : substr($time, 0, 5);
    }

    public static function toMinutes(string $time): int
    {
        [$h, $i] = array_map('intval', explode(':', $time));

        return $h * 60 + $i;
    }

    public static function fromMinutes(int $minutes): string
    {
        return sprintf('%02d:%02d', intdiv($minutes, 60), $minutes % 60);
    }

    /**
     * 画面に出す「この営業時間でできる枠」の説明（例：「月〜金 9:00〜16:30 の16枠」）。
     *
     * @return array<int,array{days:string,start:string,last:string,end:string,count:int}>
     */
    public function summary(PhotoStudio $studio): array
    {
        $studio->loadMissing('weekdayHours');
        $names = ['日', '月', '火', '水', '木', '金', '土'];
        $groups = [];
        // 月曜始まりで並べる
        foreach ([1, 2, 3, 4, 5, 6, 0] as $wd) {
            $row = $studio->weekdayHours->firstWhere('weekday', $wd);
            $start = $row ? self::hm($row->start_time) : self::hm($studio->default_start_time);
            $end = $row ? self::hm($row->end_time) : self::hm($studio->default_end_time);
            if ($start === null || $end === null) {
                continue;
            }
            $key = $start.'-'.$end;
            $groups[$key]['start'] = $start;
            $groups[$key]['end'] = $end;
            $groups[$key]['days'][] = $names[$wd];
        }

        $out = [];
        foreach ($groups as $g) {
            $times = self::timesBetween($g['start'], $g['end']);
            $out[] = [
                'days' => implode('・', $g['days']),
                'start' => $g['start'],
                'last' => end($times) ?: $g['start'],
                'end' => $g['end'],
                'count' => count($times),
            ];
        }

        return $out;
    }
}
