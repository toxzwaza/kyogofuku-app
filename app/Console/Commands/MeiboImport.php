<?php

namespace App\Console\Commands;

use App\Services\Meibo\MeiboBackup;
use App\Services\Meibo\MeiboImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * 名簿の取り込み。設計は taskboard page-28「5. データ移行と本番切替」。
 *
 *   php artisan meibo:import master.csv --dry-run     … 件数と内訳だけ見る（書かない）
 *   php artisan meibo:import master.csv               … 統合ID で差分を入れる（DM 状態・備考・履歴は守る）
 *   php artisan meibo:import persons.csv --initial --logs=dm_logs.csv
 *                                                     … 初回の移行（表が空のときだけ。DM 状態・備考・履歴も運ぶ）
 *
 * 書く前に meibo_persons・meibo_dm_logs を storage/app/private/meibo_backup/ に自動で保存する。
 * 画面・ログに氏名・住所は出さない（件数・行番号・理由だけ）。
 */
class MeiboImport extends Command
{
    protected $signature = 'meibo:import
        {file : master.csv（差分）か、初回の移行なら名簿DB の persons の書き出し。CSV か TSV、UTF-8}
        {--dry-run : 書かずに件数と内訳だけ表示する}
        {--initial : 初回の移行（meibo_persons が空のときだけ。DM 状態・DM 備考も入れる）}
        {--logs= : 初回の移行のとき、名簿DB の dm_logs の書き出し（履歴を付け替えて入れる）}
        {--no-backup : 投入前のバックアップを取らない（試験用。本番では付けない）}';

    protected $description = '名簿（成人式 DM 名簿）を CSV から取り込む（統合ID で差分 upsert。DM 状態・備考・履歴は守る）';

    public function handle(MeiboImporter $importer, MeiboBackup $backup): int
    {
        @set_time_limit(0);
        if ((int) ini_get('memory_limit') > 0 && (int) ini_get('memory_limit') < 512) {
            @ini_set('memory_limit', '512M');
        }
        $file = $this->argument('file');
        $dry = (bool) $this->option('dry-run');
        $initial = (bool) $this->option('initial');
        $logs = $this->option('logs');
        if ($logs !== null && ! $initial) {
            $this->error('--logs は --initial と一緒に使います');

            return self::FAILURE;
        }

        $this->line($dry ? '【試しに数えるだけ（--dry-run）。DB には書きません】' : '【取り込み】');

        try {
            if (! $dry) {
                if ($this->option('no-backup')) {
                    $this->warn('バックアップを取りません（--no-backup）');
                } else {
                    foreach ($backup->dump() as $table => $b) {
                        $this->line(sprintf('バックアップ：%s %s 行 → %s', $table, number_format($b['rows']), $b['path']));
                    }
                    $pruned = $backup->prune();
                    if ($pruned) {
                        $this->line("古いバックアップを {$pruned} 個消しました（" . config('meibo.backup_keep_days') . ' 日より前）');
                    }
                }
            }

            $stat = $initial
                ? $importer->initial($file, $logs, ! $dry)
                : $importer->diff($file, ! $dry);
        } catch (\Throwable $e) {
            $this->error('取り込めませんでした：' . $e->getMessage());
            Log::error('meibo:import 失敗', ['error' => $e->getMessage(), 'dry_run' => $dry, 'initial' => $initial]);

            return self::FAILURE;
        }

        $initial ? $this->reportInitial($stat) : $this->reportDiff($stat);

        Log::info('meibo:import ' . ($dry ? '(dry-run)' : '完了'), array_diff_key($stat, ['error_lines' => 1]) + ['initial' => $initial]);

        return self::SUCCESS;
    }

    private function reportDiff(array $s): void
    {
        $n = fn ($v) => number_format($v);
        $this->table(['内訳', '件数'], [
            ['CSV の行', $n($s['csv'])],
            ['新しい人（追加）', $n($s['new']) . ($s['new_with_note'] ? "（うち DM 備考あり {$n($s['new_with_note'])}）" : '')],
            ['名簿の項目が変わった人（更新）', $n($s['updated'])],
            ['変わらない人', $n($s['unchanged'])],
            ['CSV に無い人（消さずに残す）', $n($s['missing'])],
            ['エラーで入れない行', $n($s['errors'])],
            ['CSV のうち R11 成人名簿の人', $n($s['r11'])],
            ['取り込み後の名簿の人数', $n($s['total_after'])],
        ]);
        $fields = array_filter($s['changed_fields']);
        if ($fields) {
            $rows = [];
            foreach ($fields as $col => $c) {
                $rows[] = [MeiboImporter::LABELS[$col] ?? $col, $n($c)];
            }
            $this->line('更新する項目の内訳（1 人で複数の項目が変わることがあります）');
            $this->table(['項目', '件数'], $rows);
        }
        $this->line('DM 状態・DM 備考・変更履歴は今の値のまま（新しい人にだけ CSV の DM 備考を入れます）。');
        $this->errorLines($s);
    }

    private function reportInitial(array $s): void
    {
        $n = fn ($v) => number_format($v);
        $rows = [
            ['CSV の行', $n($s['csv'])],
            ['入れる人', $n($s['new'])],
        ];
        foreach ($s['status'] as $st => $c) {
            $rows[] = ["　DM 状態「{$st}」", $n($c)];
        }
        $rows[] = ['　DM 備考あり', $n($s['with_note'])];
        $rows[] = ['　R11 成人名簿の人', $n($s['r11'])];
        $rows[] = ['エラーで入れない行', $n($s['errors'])];
        if ($s['logs_csv']) {
            $rows[] = ['履歴（DM 状態の変更）の行', $n($s['logs_csv'])];
            $rows[] = ['　付け替えて入れる', $n($s['logs'])];
            $rows[] = ['　相手の人がいない（入れない）', $n($s['logs_orphan'])];
        }
        $this->table(['内訳', '件数'], $rows);
        $this->errorLines($s);
    }

    private function errorLines(array $s): void
    {
        if (! $s['errors']) {
            return;
        }
        $this->warn('エラーの行（氏名などは出しません。先頭 50 件まで）');
        foreach ($s['error_lines'] as $l) {
            $this->line('  ' . $l);
        }
    }
}
