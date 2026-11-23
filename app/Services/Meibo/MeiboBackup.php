<?php

namespace App\Services\Meibo;

use Illuminate\Support\Facades\DB;

/**
 * meibo:import の前に取るバックアップ。meibo_persons・meibo_dm_logs を gzip の CSV にして
 * storage/app/private/meibo_backup/ に日付付きで置く（フォルダ 0700・ファイル 0600。git の対象外）。
 * 残す日数は config('meibo.backup_keep_days')（既定 30）。古いものはバックアップのたびに消す。
 *
 * 戻すとき：gunzip して、表を空にしてから LOAD DATA か meibo:import --initial で入れる（手順は PR を参照）。
 */
class MeiboBackup
{
    public const TABLES = ['meibo_persons', 'meibo_dm_logs'];

    public static function dir(): string
    {
        // meibo.backup_dir は試験で置き場所を変えるためのもの（ふだんは設定しない）
        return config('meibo.backup_dir') ?: storage_path('app/private/meibo_backup');
    }

    /**
     * @return array<string, array{path: string, rows: int}>  表ごとの保存先と行数
     */
    public function dump(?\DateTimeInterface $at = null): array
    {
        $dir = self::dir();
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        @chmod($dir, 0700);
        $stamp = ($at ?? now())->format('Ymd_His');
        $out = [];
        foreach (self::TABLES as $table) {
            $path = "{$dir}/{$table}_{$stamp}.csv.gz";
            $gz = gzopen($path, 'wb6');
            if ($gz === false) {
                throw new \RuntimeException("バックアップを書けません：{$path}");
            }
            @chmod($path, 0600);
            $rows = 0;
            $header = null;
            foreach (DB::table($table)->lazyById(2000) as $r) {
                $r = (array) $r;
                if ($header === null) {
                    $header = array_keys($r);
                    gzwrite($gz, self::csvLine($header));
                }
                gzwrite($gz, self::csvLine(array_values($r)));
                $rows++;
            }
            if ($header === null) {
                gzwrite($gz, self::csvLine(DB::getSchemaBuilder()->getColumnListing($table)));
            }
            gzclose($gz);
            $out[$table] = ['path' => $path, 'rows' => $rows];
        }

        return $out;
    }

    /** 残す日数より古いバックアップを消す。消した数を返す */
    public function prune(?int $keepDays = null, ?\DateTimeInterface $now = null): int
    {
        $keepDays ??= (int) config('meibo.backup_keep_days', 30);
        if ($keepDays <= 0 || ! is_dir(self::dir())) {
            return 0;
        }
        $limit = ($now ?? now())->getTimestamp() - $keepDays * 86400;
        $n = 0;
        foreach (glob(self::dir() . '/meibo_*_*.csv.gz') ?: [] as $f) {
            if (! preg_match('/_(\d{8}_\d{6})\.csv\.gz$/', $f, $m)) {
                continue;
            }
            $t = \DateTime::createFromFormat('Ymd_His', $m[1]);
            if ($t && $t->getTimestamp() < $limit && @unlink($f)) {
                $n++;
            }
        }

        return $n;
    }

    /** NULL は \N（mysql の LOAD DATA と同じ）、ほかは CSV の引用 */
    private static function csvLine(array $vals): string
    {
        return implode(',', array_map(function ($v) {
            if ($v === null) {
                return '\\N';
            }
            $s = (string) $v;

            return preg_match('/[",\r\n\\\\]/', $s) || $s === '' ? '"' . str_replace('"', '""', $s) . '"' : $s;
        }, $vals)) . "\n";
    }
}
