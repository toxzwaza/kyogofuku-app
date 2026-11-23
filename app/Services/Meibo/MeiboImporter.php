<?php

namespace App\Services\Meibo;

use App\Models\Meibo\MeiboPerson;
use Illuminate\Support\Facades\DB;

/**
 * 名簿の取り込み（meibo:import の中身）。設計は taskboard page-28「5. データ移行と本番切替」。
 *
 * ふだんの取り込み（diff）：統合ID（master_code）で突き合わせて差分だけ入れる。
 *  - 名簿の項目（氏名・住所・電話・出所など ROSTER_COLUMNS）だけ更新する
 *  - DM 状態・DM 備考・変更履歴（meibo_dm_logs）は本番の値を守る。新しい人にだけ CSV の DM 備考を入れる
 *  - CSV に無い人は消さない（件数を出すだけ）。id は変えない（行を作り直さない）
 *
 * 初回の移行（initial）：名簿DB（Mac の kyogofuku_meibo）の persons／dm_logs の書き出しから入れる。
 *  - meibo_persons が空のときだけ。DM 状態・備考もそのまま運ぶ（いまの正本は名簿DB）
 *  - 履歴は名簿DB の person_id → 統合ID → こちらの id に付け替える（誰が変えたかは空）
 *
 * どちらも 1 つのトランザクションで書く（途中で失敗したら何も入らない）。
 * 画面・ログに氏名・住所は出さない（エラーは行番号と理由だけ）。
 */
class MeiboImporter
{
    /** CSV から更新する名簿の項目（DM 状態・備考は入れない） */
    public const ROSTER_COLUMNS = [
        'pref', 'seijin_year', 'birth_fy', 'name', 'kana', 'birth_date', 'gender',
        'zip', 'address', 'tel', 'mobile', 'guardian_name', 'guardian_kana',
        'source_versions', 'merge_count',
    ];

    /** 項目の日本語名（内訳の表示用） */
    public const LABELS = [
        'pref' => '都道府県', 'seijin_year' => '成人式年', 'birth_fy' => '生年度', 'name' => '本人氏名',
        'kana' => '本人フリガナ', 'birth_date' => '生年月日', 'gender' => '性別', 'zip' => '郵便番号',
        'address' => '住所', 'tel' => '固定電話', 'mobile' => '携帯電話', 'guardian_name' => '保護者氏名',
        'guardian_kana' => '保護者フリガナ', 'source_versions' => '統合元版', 'merge_count' => '統合件数',
    ];

    /** 列の長さ（migration と同じ。超える行はエラーにして入れない） */
    private const MAX_LEN = [
        'master_code' => 12, 'pref' => 10, 'name' => 100, 'kana' => 100, 'gender' => 4, 'zip' => 10,
        'address' => 255, 'tel' => 20, 'mobile' => 20, 'guardian_name' => 100, 'guardian_kana' => 100,
        'source_versions' => 255, 'dm_note' => 255,
    ];

    private const CHUNK = 1000;

    /**
     * ふだんの取り込み。$write=false なら数えるだけ（--dry-run）
     *
     * @return array 件数（csv・new・updated・unchanged・missing・errors・changed_fields・new_with_note・r11・error_lines）
     */
    public function diff(string $path, bool $write): array
    {
        $reader = new MeiboCsvReader($path);
        $this->requireColumns($reader, ['master_code', 'name']);

        $stat = [
            'csv' => 0, 'new' => 0, 'updated' => 0, 'unchanged' => 0, 'missing' => 0, 'errors' => 0,
            'new_with_note' => 0, 'r11' => 0, 'changed_fields' => array_fill_keys(self::ROSTER_COLUMNS, 0),
            'error_lines' => [],
        ];
        $seen = [];
        $now = now();

        $run = function () use ($reader, &$stat, &$seen, $write, $now) {
            $buf = [];
            $flush = function () use (&$buf, &$stat, $write, $now) {
                if (! $buf) {
                    return;
                }
                $existing = DB::table('meibo_persons')
                    ->whereIn('master_code', array_keys($buf))
                    ->get(array_merge(['id', 'master_code'], self::ROSTER_COLUMNS))
                    ->keyBy('master_code');
                $inserts = [];
                foreach ($buf as $code => $row) {
                    $cur = $existing->get($code);
                    if (! $cur) {
                        $stat['new']++;
                        if (($row['dm_note'] ?? '') !== '') {
                            $stat['new_with_note']++;
                        }
                        $inserts[] = $this->onlyRoster($row) + [
                            'master_code' => $code,
                            'dm_status' => '送付可',
                            'dm_note' => (string) ($row['dm_note'] ?? ''),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        continue;
                    }
                    $changes = [];
                    foreach (self::ROSTER_COLUMNS as $col) {
                        if (self::norm($cur->{$col}) !== self::norm($row[$col] ?? null)) {
                            $changes[$col] = $row[$col] ?? null;
                            $stat['changed_fields'][$col]++;
                        }
                    }
                    if (! $changes) {
                        $stat['unchanged']++;

                        continue;
                    }
                    $stat['updated']++;
                    if ($write) {
                        DB::table('meibo_persons')->where('id', $cur->id)->update($changes + ['updated_at' => $now]);
                    }
                }
                if ($write && $inserts) {
                    foreach (array_chunk($inserts, 500) as $part) {
                        DB::table('meibo_persons')->insert($part);
                    }
                }
                $buf = [];
            };

            foreach ($reader->rows() as [$lineNo, $raw]) {
                $stat['csv']++;
                [$row, $err] = $this->clean($raw);
                if (! $err && isset($seen[$row['master_code']])) {
                    $err = '統合ID が CSV の中で重複（' . $seen[$row['master_code']] . '行目と同じ）';
                }
                if ($err) {
                    $this->addError($stat, $lineNo, $err);

                    continue;
                }
                $seen[$row['master_code']] = $lineNo;
                if (MeiboPerson::R11_SOURCE && in_array(MeiboPerson::R11_SOURCE, explode('/', (string) ($row['source_versions'] ?? '')), true)) {
                    $stat['r11']++;
                }
                $buf[$row['master_code']] = $row;
                if (count($buf) >= self::CHUNK) {
                    $flush();
                }
            }
            $flush();
        };

        $write ? DB::transaction($run) : $run();

        // CSV に無い人（消さない。数えるだけ）
        $total = DB::table('meibo_persons')->count();
        $inCsv = $stat['new'] + $stat['updated'] + $stat['unchanged'];
        $stat['missing'] = max(0, $total - ($write ? $inCsv : $inCsv - $stat['new']));
        $stat['total_after'] = $write ? $total : $total + $stat['new'];

        return $stat;
    }

    /**
     * 初回の移行。$personsPath は名簿DB の persons、$logsPath は dm_logs（無くてもよい）
     */
    public function initial(string $personsPath, ?string $logsPath, bool $write): array
    {
        if (DB::table('meibo_persons')->exists()) {
            throw new \RuntimeException('meibo_persons にすでに人が入っています。初回の移行（--initial）は空の表にだけ入れます。2 回目からは --initial を付けずに差分で取り込んでください');
        }
        $reader = new MeiboCsvReader($personsPath);
        $this->requireColumns($reader, ['master_code', 'name']);
        $hasOldId = $reader->has('id');

        $stat = [
            'csv' => 0, 'new' => 0, 'errors' => 0, 'error_lines' => [], 'r11' => 0,
            'status' => array_fill_keys(MeiboPerson::STATUSES, 0), 'with_note' => 0,
            'logs_csv' => 0, 'logs' => 0, 'logs_orphan' => 0,
        ];
        $now = now();

        $run = function () use ($reader, $logsPath, $hasOldId, &$stat, $write, $now) {
            $oldToCode = [];
            $seen = [];
            $inserts = [];
            $flush = function () use (&$inserts, $write) {
                if ($write && $inserts) {
                    DB::table('meibo_persons')->insert($inserts);
                }
                $inserts = [];
            };
            foreach ($reader->rows() as [$lineNo, $raw]) {
                $stat['csv']++;
                [$row, $err] = $this->clean($raw);
                $status = $raw['dm_status'] ?? null;
                if (! $err && $status !== null && ! in_array($status, MeiboPerson::STATUSES, true)) {
                    $err = 'DM 状態が「送付可・未達・停止」のどれでもない';
                }
                if (! $err && isset($seen[$row['master_code']])) {
                    $err = '統合ID が重複（' . $seen[$row['master_code']] . '行目と同じ）';
                }
                if ($err) {
                    $this->addError($stat, $lineNo, $err);

                    continue;
                }
                $seen[$row['master_code']] = $lineNo;
                if ($hasOldId && ! empty($raw['id'])) {
                    $oldToCode[(int) $raw['id']] = $row['master_code'];
                }
                $status ??= '送付可';
                $stat['new']++;
                $stat['status'][$status]++;
                if (($row['dm_note'] ?? '') !== '') {
                    $stat['with_note']++;
                }
                if (in_array(MeiboPerson::R11_SOURCE, explode('/', (string) ($row['source_versions'] ?? '')), true)) {
                    $stat['r11']++;
                }
                $inserts[] = $this->onlyRoster($row) + [
                    'master_code' => $row['master_code'],
                    'dm_status' => $status,
                    'dm_note' => (string) ($row['dm_note'] ?? ''),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
                if (count($inserts) >= 500) {
                    $flush();
                }
            }
            $flush();

            if ($logsPath === null) {
                return;
            }
            if (! $hasOldId) {
                throw new \RuntimeException('履歴を付け替えるには、persons の書き出しに id 列（名簿DB の id）が要ります');
            }
            $logs = new MeiboCsvReader($logsPath);
            $this->requireColumns($logs, ['person_id', 'new_status']);
            $codeToNew = $write
                ? DB::table('meibo_persons')->pluck('id', 'master_code')->all()
                : array_flip(array_values($oldToCode)); // 数えるだけのときは「いるかどうか」だけ見る
            $buf = [];
            foreach ($logs->rows() as [$lineNo, $raw]) {
                $stat['logs_csv']++;
                $code = $raw ? ($oldToCode[(int) ($raw['person_id'] ?? 0)] ?? null) : null;
                if ($code === null || ! isset($codeToNew[$code])) {
                    $stat['logs_orphan']++;

                    continue;
                }
                $stat['logs']++;
                $buf[] = [
                    'person_id' => $codeToNew[$code],
                    'old_status' => self::cut($raw['old_status'] ?? null, 10),
                    'new_status' => self::cut($raw['new_status'] ?? null, 10),
                    'old_note' => null,
                    'note' => self::cut($raw['note'] ?? null, 255),
                    'changed_by_user_id' => null,
                    'changed_at' => $raw['changed_at'] ?? $now,
                ];
                if (count($buf) >= 500) {
                    if ($write) {
                        DB::table('meibo_dm_logs')->insert($buf);
                    }
                    $buf = [];
                }
            }
            if ($write && $buf) {
                DB::table('meibo_dm_logs')->insert($buf);
            }
        };

        $write ? DB::transaction($run) : $run();

        return $stat;
    }

    // ---------------------------------------------------------------- 1 行の形

    /**
     * 1 行を表に入れる形にする。[$row, エラーの理由 or null]
     */
    private function clean(?array $raw): array
    {
        if ($raw === null) {
            return [null, '列の数が見出しと合わない'];
        }
        $row = [];
        $row['master_code'] = $raw['master_code'] ?? null;
        if ($row['master_code'] === null) {
            return [null, '統合ID が空'];
        }
        if (! preg_match('/^[A-Za-z0-9_-]+$/', $row['master_code'])) {
            return [null, '統合ID の形が違う'];
        }
        foreach (self::ROSTER_COLUMNS as $col) {
            $row[$col] = $raw[$col] ?? null;
        }
        $row['dm_note'] = $raw['dm_note'] ?? null;
        if ($row['name'] === null) {
            return [null, '本人氏名が空'];
        }
        foreach (['seijin_year', 'birth_fy'] as $col) {
            if ($row[$col] !== null) {
                if (! preg_match('/^\d{4}$/', $row[$col])) {
                    return [null, self::LABELS[$col] . 'が西暦 4 桁でない'];
                }
                $row[$col] = (int) $row[$col];
            }
        }
        $row['merge_count'] = $row['merge_count'] === null ? 1 : (int) $row['merge_count'];
        if ($row['birth_date'] !== null) {
            $d = self::date($row['birth_date']);
            if ($d === null) {
                return [null, '生年月日が日付でない'];
            }
            $row['birth_date'] = $d;
        }
        foreach (self::MAX_LEN as $col => $max) {
            if (isset($row[$col]) && mb_strlen((string) $row[$col]) > $max) {
                return [null, (self::LABELS[$col] ?? ($col === 'master_code' ? '統合ID' : 'DM 備考')) . "が長すぎる（{$max} 文字まで）"];
            }
        }

        return [$row, null];
    }

    private function onlyRoster(array $row): array
    {
        return array_intersect_key($row, array_flip(self::ROSTER_COLUMNS));
    }

    private function requireColumns(MeiboCsvReader $r, array $cols): void
    {
        $missing = array_values(array_filter($cols, fn ($c) => ! $r->has($c)));
        if ($missing) {
            throw new \RuntimeException('見出しに必要な列がありません：' . implode('・', $missing) . '（master.csv の日本語の見出しか、名簿DB の列名にしてください）');
        }
    }

    private function addError(array &$stat, int $lineNo, string $why): void
    {
        $stat['errors']++;
        if (count($stat['error_lines']) < 50) {
            $stat['error_lines'][] = "{$lineNo}行目：{$why}";
        }
    }

    /** 比べるときの形（null と '' は同じ、数は文字に、日付は Y-m-d） */
    private static function norm($v): string
    {
        if ($v === null) {
            return '';
        }
        if ($v instanceof \DateTimeInterface) {
            return $v->format('Y-m-d');
        }
        $s = trim((string) $v);
        if (preg_match('/^(\d{4}-\d{2}-\d{2}) 00:00:00$/', $s, $m)) {
            return $m[1];
        }

        return $s;
    }

    private static function date(string $v): ?string
    {
        if (preg_match('/^(\d{4})[-\/.](\d{1,2})[-\/.](\d{1,2})(?:[ T]00:00(?::00)?)?$/', $v, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1])) {
            return sprintf('%04d-%02d-%02d', $m[1], $m[2], $m[3]);
        }

        return null;
    }

    private static function cut(?string $v, int $max): ?string
    {
        return $v === null ? null : mb_substr($v, 0, $max);
    }
}
