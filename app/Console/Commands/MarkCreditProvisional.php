<?php

namespace App\Console\Commands;

use App\Models\CreditContract;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * 取り込み済みのクレジット契約を「仮登録（内容を確認中）」にする。
 *
 *  - csv             : 取り込み元（ファイル#列）,確認していること  の CSV（1行目は見出し）
 *  - --unknown-company : 信販会社が空の契約（解約を除く）も仮登録にする（確認していること＝信販会社がわからない）
 *  - --dry-run       : 対象を数えるだけで書き込まない
 *
 * 既に仮登録の契約は、確認していることだけ上書きする。確認済み（仮登録を外した）の契約は触らない。
 */
class MarkCreditProvisional extends Command
{
    protected $signature = 'credit:provisional
        {csv? : 取り込み元,確認していること の CSV}
        {--unknown-company : 信販会社が空の契約も仮登録にする}
        {--dry-run : 書き込まずに対象を数えるだけ}';

    protected $description = 'クレジット契約を仮登録（担当の方に内容を確認中）にする';

    public const UNKNOWN_COMPANY_NOTE = '信販会社がわからない（担当の方に確認中）';

    public function handle(): int
    {
        $targets = []; // 契約ID => 確認していること
        $missing = [];

        if ($csv = $this->argument('csv')) {
            if (! is_file($csv)) {
                $this->error("CSV が見つかりません: {$csv}");

                return self::FAILURE;
            }
            $fh = fopen($csv, 'r');
            $header = fgetcsv($fh);
            if (isset($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            }
            while (($row = fgetcsv($fh)) !== false) {
                [$src, $note] = array_pad($row, 2, '');
                $src = trim($src);
                if ($src === '') {
                    continue;
                }
                $contract = CreditContract::query()->where('source_file', $src)->first();
                if (! $contract) {
                    $missing[] = $src;
                    continue;
                }
                $targets[$contract->id] = trim($note);
            }
            fclose($fh);
        }

        if ($this->option('unknown-company')) {
            CreditContract::query()->whereNull('credit_company_id')
                ->where('status', '!=', CreditContract::STATUS_CANCELLED)->pluck('id')
                ->each(function ($id) use (&$targets) {
                    $targets[$id] = isset($targets[$id]) && $targets[$id] !== ''
                        ? $targets[$id]
                        : self::UNKNOWN_COMPANY_NOTE;
                });
        }

        $contracts = CreditContract::query()->whereIn('id', array_keys($targets))->get();
        $skipped = $contracts->filter(fn ($c) => ! $c->is_provisional && $c->confirmed_at !== null);
        $apply = $contracts->reject(fn ($c) => ! $c->is_provisional && $c->confirmed_at !== null);

        $this->table(['項目', '件数'], [
            ['仮登録にする', $apply->where('is_provisional', false)->count()],
            ['既に仮登録（確認していることを更新）', $apply->where('is_provisional', true)->count()],
            ['確認済みのため触らない', $skipped->count()],
            ['CSV にあるが契約が見つからない', count($missing)],
        ]);
        foreach ($missing as $m) {
            $this->warn("見つからない：{$m}");
        }

        if ($this->option('dry-run')) {
            $this->info('dry-run のため書き込んでいません。');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($apply, $targets) {
            foreach ($apply as $c) {
                $c->update(['is_provisional' => true, 'provisional_note' => $targets[$c->id] ?: null]);
            }
        });
        $this->info('仮登録にしました：' . $apply->count() . ' 件');

        return self::SUCCESS;
    }
}
