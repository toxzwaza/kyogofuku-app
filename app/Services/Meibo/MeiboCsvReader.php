<?php

namespace App\Services\Meibo;

/**
 * 名簿の CSV/TSV を 1 行ずつ読む（行を溜めない）。
 *
 * 読める形：
 *  - 名簿管理システムの master.csv（UTF-8・BOM あり・見出しは日本語：統合ID・都道府県…・統合元版・統合件数［・DM備考］）
 *  - 名簿DB（kyogofuku_meibo）の persons / dm_logs を書き出したもの（見出しは列名：master_code・pref…）
 *    ・load_db.py のバックアップ CSV（data/backup/persons_*.csv。空欄＝NULL）
 *    ・mysql -B -e "SELECT * FROM persons" の TSV（NULL は文字の NULL、\t \n \\ はエスケープ）
 *
 * 見出しは列名（master_code など）にそろえて返す。区切りは 1 行目にタブがあれば TSV とみなす。
 */
class MeiboCsvReader
{
    /** master.csv の日本語の見出し → 列名 */
    public const JA_HEADERS = [
        '統合ID' => 'master_code',
        '都道府県' => 'pref',
        '成人式年' => 'seijin_year',
        '生年度' => 'birth_fy',
        '本人氏名' => 'name',
        '本人フリガナ' => 'kana',
        '生年月日' => 'birth_date',
        '性別' => 'gender',
        '郵便番号' => 'zip',
        '住所' => 'address',
        '固定電話' => 'tel',
        '携帯電話' => 'mobile',
        '保護者氏名' => 'guardian_name',
        '保護者フリガナ' => 'guardian_kana',
        '統合元版' => 'source_versions',
        '統合件数' => 'merge_count',
        'DM備考' => 'dm_note',
        'DM状態' => 'dm_status',
    ];

    private $fh;
    private bool $tsv;
    private array $header;
    private int $line = 1;

    public function __construct(private string $path)
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new \RuntimeException("ファイルを読めません：{$path}");
        }
        $this->fh = fopen($path, 'rb');
        $first = fgets($this->fh);
        if ($first === false) {
            throw new \RuntimeException('ファイルが空です');
        }
        $first = preg_replace('/^\xEF\xBB\xBF/', '', $first);
        if (! mb_check_encoding($first, 'UTF-8')) {
            throw new \RuntimeException('UTF-8 ではありません（Shift-JIS などは UTF-8 にしてから入れてください）');
        }
        $this->tsv = str_contains($first, "\t");
        $cols = $this->tsv
            ? explode("\t", rtrim($first, "\r\n"))
            : str_getcsv(rtrim($first, "\r\n"));
        $this->header = array_map(fn ($h) => self::JA_HEADERS[trim($h)] ?? trim($h), $cols);
    }

    public function __destruct()
    {
        if (is_resource($this->fh)) {
            fclose($this->fh);
        }
    }

    /** 列名にそろえた見出し */
    public function header(): array
    {
        return $this->header;
    }

    public function has(string $col): bool
    {
        return in_array($col, $this->header, true);
    }

    /**
     * 1 行ずつ [行番号, [列名 => 値（空・NULL は null）]] を返す。列の数が合わない行は値が null の配列で返す（呼び出し側でエラーにする）
     */
    public function rows(): \Generator
    {
        $n = count($this->header);
        while (true) {
            $this->line++;
            $lineNo = $this->line;
            if ($this->tsv) {
                $raw = fgets($this->fh);
                if ($raw === false) {
                    return;
                }
                $raw = rtrim($raw, "\r\n");
                if ($raw === '') {
                    continue;
                }
                $vals = array_map(fn ($v) => $v === 'NULL' ? null : self::unescapeTsv($v), explode("\t", $raw));
            } else {
                $vals = fgetcsv($this->fh, 0, ',', '"', '');
                if ($vals === false) {
                    return;
                }
                if ($vals === [null]) {
                    continue; // 空行
                }
                // 引用符の中の改行で行番号がずれないよう、実際に読んだ位置から数え直しはしない（エラー表示の目安）
            }
            if (count($vals) !== $n) {
                yield [$lineNo, null];

                continue;
            }
            $row = [];
            foreach ($this->header as $i => $col) {
                $v = $vals[$i];
                $row[$col] = ($v === null || trim($v) === '') ? null : trim($v);
            }
            yield [$lineNo, $row];
        }
    }

    private static function unescapeTsv(string $v): string
    {
        return strtr($v, ['\\t' => "\t", '\\n' => "\n", '\\r' => "\r", '\\0' => "\0", '\\\\' => '\\']);
    }
}
