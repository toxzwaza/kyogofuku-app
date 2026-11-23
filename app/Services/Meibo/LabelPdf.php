<?php

namespace App\Services\Meibo;

/**
 * 宛名ラベルの PDF（A4／A-One 66732 相当：2 列×5 段＝10 面）。
 *
 * 名簿管理システム（Mac の FastAPI 版）の backend/labels.py（reportlab）を、同じ位置・見た目で TCPDF に移したもの。
 * 1 面：W86.4mm × H50.8mm／余白：天 21.2・左 18.6（面のあいだの隙間なし）。
 * 左上に料金後納（円）＋ゆうメール、右上に統合ID の QR、宛先の〒・住所・氏名（明朝）、下に差出人・担当。
 *
 * 座標は labels.py の値をそのまま使う。labels.py は「面の上端から何 mm 下か」（Ytop）で文字の
 * ベースラインを指定しているので、ここでも上端基準の mm をベースラインとして渡す（Text の calign='L'）。
 * 文字の大きさ・字間・線の太さは pt（labels.py と同じ数）で書き、描くときに mm に直す。
 *
 * 件数が多くても完走するよう、1 人ずつ描く（add）だけで行を溜めない。QR は 1 面ずつ
 * 「1 モジュール＝1 単位」に座標を変えて横の連続を 1 つの矩形にまとめ、短い命令で書く。
 * 日本語は TCPDF 同梱の CID フォント（埋め込まない。reportlab の HeiseiKakuGo-W5／HeiseiMin-W3 と同じ扱い）。
 */
class LabelPdf
{
    public const COLS = 2;
    public const ROWS = 5;
    public const PER_PAGE = self::COLS * self::ROWS;
    public const LW = 86.4;
    public const LH = 50.8;
    public const M_TOP = 21.2;
    public const M_LEFT = 18.6;

    /** ゴシック（labels.py の HeiseiKakuGo-W5）・明朝（HeiseiMin-W3） */
    public const GO = 'kozgopromedium';
    public const MIN = 'kozminproregular';

    private const INK = [26, 26, 26];    // #1a1a1a 本文
    private const SUB = [68, 68, 68];    // #444444 補助
    private const RULE = [189, 189, 189]; // #bdbdbd 区切り線
    private const GUIDE = [216, 216, 216]; // #d8d8d8 面の枠（位置合わせ用）

    public const DEFAULT_SENDER = [
        'zip' => '918-8015',
        'address' => '福井県福井市花堂南1-2-1',
        'company' => '(株)京呉服　平田',
    ];

    private LabelTcpdf $pdf;
    private int $count = 0;
    private array $sender;
    private string $addressee;
    private bool $withFamily;
    private string $tantou;
    private bool $guide;

    /** 文字幅の覚え（同じ文字・同じ大きさを何度も測らない） */
    private array $widthCache = [];

    /**
     * @param  array  $opts  sender（zip/address/company）・addressee（self/guardian）・with_family・tantou・guide（面の枠を描く）
     */
    public function __construct(array $opts = [])
    {
        $this->sender = array_merge(self::DEFAULT_SENDER, array_filter($opts['sender'] ?? [], fn ($v) => $v !== null && $v !== ''));
        $this->addressee = ($opts['addressee'] ?? 'self') === 'guardian' ? 'guardian' : 'self';
        $this->withFamily = (bool) ($opts['with_family'] ?? false);
        $this->tantou = (string) ($opts['tantou'] ?? '');
        $this->guide = (bool) ($opts['guide'] ?? false);

        $pdf = new LabelTcpdf('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->setCreator('kyogofuku-app');
        $pdf->setTitle('宛名ラベル');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->setMargins(0, 0, 0);
        $pdf->setAutoPageBreak(false, 0);
        $pdf->setCellPaddings(0, 0, 0, 0);
        $pdf->setCellMargins(0, 0, 0, 0);
        $pdf->setCompression(true);
        $pdf->useLabelFonts();
        $this->pdf = $pdf;
    }

    public function count(): int
    {
        return $this->count;
    }

    /** 1 人分を次の面に描く。$row は master_code・name・guardian_name・pref・zip・address */
    public function add(array $row): void
    {
        $idx = $this->count % self::PER_PAGE;
        if ($idx === 0) {
            $this->pdf->AddPage();
        }
        $x0 = self::M_LEFT + ($idx % self::COLS) * self::LW;
        $y0 = self::M_TOP + intdiv($idx, self::COLS) * self::LH;
        $this->draw($x0, $y0, $row);
        $this->count++;
    }

    /** ファイルに書き出す（大きい PDF を文字列で二重に持たないよう、応答はこのファイルを流す） */
    public function save(string $path): void
    {
        if ($this->count === 0) {
            $this->pdf->AddPage();
        }
        $this->pdf->Output($path, 'F');
    }

    public function output(): string
    {
        if ($this->count === 0) {
            $this->pdf->AddPage();
        }

        return $this->pdf->Output('labels.pdf', 'S');
    }

    // ---------------------------------------------------------------- 1 面

    private function draw(float $x0, float $y0, array $row): void
    {
        $pdf = $this->pdf;
        $X = fn (float $mm) => $x0 + $mm;
        $Y = fn (float $mm) => $y0 + $mm;

        if ($this->guide) {
            $pdf->setDrawColorArray(self::GUIDE);
            $pdf->setLineWidth(self::pt(0.3));
            $pdf->Rect($x0, $y0, self::LW, self::LH, 'D');
        }

        // ===== 左上：料金後納（円）＋ゆうメール =====
        $this->circleIndicia($X(9.6), $Y(9.4), 6.2);
        $this->pill($X(3.0), $Y(23.3 - 5.6), 13.4, 5.6, 'ゆうメール');

        // ===== 右上：QR＋管理番号 =====
        $code = (string) ($row['master_code'] ?? '');
        $qrSize = 15.0; // クワイエットゾーン込み
        if ($code !== '') {
            $this->qr($code, $X(68.2), $Y(16.6 - $qrSize), $qrSize);
        }
        $this->text(self::GO, 6.4, self::SUB, $X(75.7), $Y(19.6), $code, 'C');

        // ===== 宛先 郵便番号 =====
        $this->text(self::GO, 11.5, self::INK, $X(19.5), $Y(7.6), '〒 ' . ($row['zip'] ?? ''), 'L', 0.6);

        // ===== 宛先 住所（QR に被らない幅で折り返す。3 行に収まるまで小さくする）=====
        $addr = (string) ($row['pref'] ?? '') . (string) ($row['address'] ?? '');
        $addrW = 46.5;
        $size = 8.6;
        $lines = $this->wrap($addr, self::GO, $size, $addrW);
        while (count($lines) > 3 && $size > 7.0) {
            $size -= 0.5;
            $lines = $this->wrap($addr, self::GO, $size, $addrW);
        }
        $ay = 13.2;
        foreach (array_slice($lines, 0, 3) as $line) {
            $this->text(self::GO, $size, self::INK, $X(19.5), $Y($ay), $line);
            $ay += 4.4;
        }

        // ===== 宛先 氏名（主役・明朝・字間広め）=====
        $name = $this->addressee === 'guardian' && ! empty($row['guardian_name'])
            ? (string) $row['guardian_name']
            : (string) ($row['name'] ?? '');
        $disp = $name . '　様';
        $csp = 1.6;
        $size = $this->fit($disp, self::MIN, 62, 22, 12, $csp);
        $this->text(self::MIN, $size, self::INK, $X(20.5), $Y(31.2), $disp, 'L', $csp);

        // ===== ご家族様（任意）=====
        if ($this->withFamily) {
            $this->text(self::MIN, 12.5, self::INK, $X(33.0), $Y(38.6), 'ご家族様');
        }

        // ===== 差出人（下部・区切り線つき）=====
        $pdf->setDrawColorArray(self::RULE);
        $pdf->setLineWidth(self::pt(0.5));
        $pdf->Line($X(3.0), $Y(40.6), $X(83.4), $Y(40.6));
        $this->text(self::GO, 6.2, self::SUB, $X(3.0), $Y(39.7), '差出人');
        $this->text(self::GO, 7.4, self::INK, $X(3.0), $Y(44.7), '〒' . $this->sender['zip'] . '　' . $this->sender['address']);
        $this->text(self::GO, 8.4, self::INK, $X(3.0), $Y(49.0), (string) $this->sender['company']);

        // ===== 担当名（任意・右下）=====
        if ($this->tantou !== '') {
            $this->text(self::GO, 7.6, self::SUB, $X(83.4), $Y(49.0), '担当：' . $this->tantou, 'R');
        }
    }

    // ---------------------------------------------------------------- 部品

    /** 料金後納郵便の円（1 重線） */
    private function circleIndicia(float $cx, float $cy, float $r): void
    {
        $pdf = $this->pdf;
        $pdf->setLineWidth(self::pt(0.9));
        $pdf->setDrawColorArray(self::INK);
        $pdf->Circle($cx, $cy, $r, 0, 360, 'D');
        $this->text(self::GO, 5.7, self::INK, $cx, $cy - 0.9, '料金後納', 'C');
        $this->text(self::GO, 5.9, self::INK, $cx, $cy + 3.2, '郵便', 'C');
    }

    /** ゆうメールのピル型（$y は上端） */
    private function pill(float $x, float $y, float $w, float $h, string $text): void
    {
        $pdf = $this->pdf;
        $pdf->setLineWidth(self::pt(0.9));
        $pdf->setDrawColorArray(self::INK);
        $pdf->RoundedRect($x, $y, $w, $h, $h / 2, '1111', 'D');
        $size = $this->fit($text, self::GO, $w - 3, 7.6, 6);
        // labels.py：ベースライン＝下端から h/2 − size×0.35pt 上
        $this->text(self::GO, $size, self::INK, $x + $w / 2, $y + $h / 2 + self::pt($size * 0.35), $text, 'C');
    }

    /**
     * 文字を書く。$x・$baseline は mm（ページの左上基準）。$cs は字間（pt。labels.py の _draw_spaced と同じく文字ごとに足す）
     * $align：L＝$x から右へ、C＝$x が中央、R＝$x が右端
     */
    private function text(string $font, float $sizePt, array $color, float $x, float $baseline, string $txt, string $align = 'L', float $cs = 0): void
    {
        if ($txt === '') {
            return;
        }
        $pdf = $this->pdf;
        $pdf->setFont($font, '', $sizePt);
        $pdf->setTextColorArray($color);
        if ($align !== 'L') {
            $w = $this->width($txt, $font, $sizePt, $cs);
            $x -= $align === 'C' ? $w / 2 : $w;
        }
        $pdf->setFontSpacing(self::pt($cs));
        $pdf->Text($x, $baseline, $txt, 0, false, true, 0, 0, '', false, '', 0, false, 'L', 'T');
        if ($cs) {
            $pdf->setFontSpacing(0);
        }
    }

    /** 文字列の幅（mm）。字間 $cs（pt）は文字のあいだに足す */
    private function width(string $txt, string $font, float $sizePt, float $cs = 0): float
    {
        $w = 0.0;
        $chars = mb_str_split($txt);
        foreach ($chars as $ch) {
            $w += $this->charWidth($ch, $font, $sizePt);
        }

        return $w + self::pt($cs) * max(count($chars) - 1, 0);
    }

    private function charWidth(string $ch, string $font, float $sizePt): float
    {
        // 1pt あたりの幅を覚えておき、大きさを掛ける
        $key = $font . "\0" . $ch;
        if (! isset($this->widthCache[$key])) {
            $this->widthCache[$key] = $this->pdf->GetStringWidth($ch, $font, '', 100) / 100;
        }

        return $this->widthCache[$key] * $sizePt;
    }

    /** labels.py の _fit_font：幅 $maxMm に入るまで 0.5pt ずつ小さく（最小 $min） */
    private function fit(string $txt, string $font, float $maxMm, float $start, float $min = 10.5, float $cs = 0): float
    {
        $size = $start;
        while ($size > $min) {
            if ($this->width($txt, $font, $size, $cs) <= $maxMm) {
                break;
            }
            $size -= 0.5;
        }

        return $size;
    }

    /** labels.py の _wrap_measure：実寸で $maxMm を超えないよう 1 文字ずつ折り返す */
    private function wrap(string $txt, string $font, float $sizePt, float $maxMm): array
    {
        $lines = [];
        $cur = '';
        $w = 0.0;
        foreach (mb_str_split($txt) as $ch) {
            $cw = $this->charWidth($ch, $font, $sizePt);
            if ($cur !== '' && $w + $cw > $maxMm) {
                $lines[] = $cur;
                $cur = $ch;
                $w = $cw;
            } else {
                $cur .= $ch;
                $w += $cw;
            }
        }
        if ($cur !== '') {
            $lines[] = $cur;
        }

        return $lines ?: [''];
    }

    /**
     * QR（誤り訂正 L・標準 QR）。$x・$y は左上（mm）、$size はクワイエットゾーン 4 モジュール込みの一辺（mm）。
     * 横に続く黒モジュールを 1 つの矩形にまとめ、1 本のパスで塗る（labels.py の _draw_qr と同じ考え）。
     */
    private function qr(string $data, float $x, float $y, float $size, int $border = 4): void
    {
        $bc = (new \TCPDF2DBarcode($data, 'QRCODE,L'))->getBarcodeArray();
        if (! $bc || empty($bc['bcode'])) {
            return;
        }
        $n = (int) $bc['num_rows'];
        $cell = $size / ($n + 2 * $border);
        $runs = [];
        foreach ($bc['bcode'] as $r => $line) {
            $c = 0;
            while ($c < $n) {
                if (! empty($line[$c])) {
                    $s = $c;
                    while ($c < $n && ! empty($line[$c])) {
                        $c++;
                    }
                    $runs[] = [$s, $r, $c - $s];
                } else {
                    $c++;
                }
            }
        }
        $this->pdf->modulePath($x + $border * $cell, $y + $border * $cell, $cell, $runs);
    }

    /** pt → mm */
    public static function pt(float $pt): float
    {
        return $pt * 25.4 / 72;
    }
}
