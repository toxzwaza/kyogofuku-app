<?php

namespace App\Services\Meibo;

/**
 * TCPDF に「モジュール単位の矩形をまとめて塗る」命令だけ足したもの（QR を短い命令で書くため）。
 */
class LabelTcpdf extends \TCPDF
{
    /**
     * 半角文字の幅（1/1000 em）。labels.py（reportlab の HeiseiKakuGo-W5／HeiseiMin-W3）と同じ値にして、
     * 〒番号・番地・管理番号などの半角の並びが名簿管理システムの PDF と同じ位置になるようにする
     * （TCPDF 同梱の kozgopromedium／kozminproregular は半角の幅が少し違い、半角カナを全角幅で持っている）。
     */
    private const HALF_WIDTHS = [
        'kozgopromedium' => [
        32=>277, 33=>305, 34=>500, 35=>668, 36=>668, 37=>906, 38=>727, 39=>305, 40=>445, 41=>445, 42=>508, 43=>668,
        44=>305, 45=>379, 46=>305, 47=>539, 48=>668, 49=>668, 50=>668, 51=>668, 52=>668, 53=>668, 54=>668, 55=>668,
        56=>668, 57=>668, 58=>305, 59=>305, 60=>668, 61=>668, 62=>668, 63=>566, 64=>871, 65=>727, 66=>637, 67=>652,
        68=>699, 69=>574, 70=>555, 71=>676, 72=>687, 73=>242, 74=>492, 75=>664, 76=>582, 77=>789, 78=>707, 79=>734,
        80=>582, 81=>734, 82=>605, 83=>605, 84=>641, 85=>668, 86=>727, 87=>945, 88=>609, 89=>609, 90=>574, 91=>445,
        92=>1000, 93=>445, 94=>668, 95=>668, 96=>590, 97=>555, 98=>609, 99=>547, 100=>602, 101=>574, 102=>391, 103=>609,
        104=>582, 105=>234, 106=>277, 107=>539, 108=>234, 109=>895, 110=>582, 111=>605, 112=>602, 113=>602, 114=>387, 115=>508,
        116=>441, 117=>582, 118=>562, 119=>781, 120=>531, 121=>570, 122=>555, 123=>449, 124=>246, 125=>449, 126=>668,
        ],
        'kozminproregular' => [
        32=>250, 33=>333, 34=>408, 35=>500, 36=>500, 37=>833, 38=>778, 39=>180, 40=>333, 41=>333, 42=>500, 43=>564,
        44=>250, 45=>333, 46=>250, 47=>278, 48=>500, 49=>500, 50=>500, 51=>500, 52=>500, 53=>500, 54=>500, 55=>500,
        56=>500, 57=>500, 58=>278, 59=>278, 60=>564, 61=>564, 62=>564, 63=>444, 64=>921, 65=>722, 66=>667, 67=>667,
        68=>722, 69=>611, 70=>556, 71=>722, 72=>722, 73=>333, 74=>389, 75=>722, 76=>611, 77=>889, 78=>722, 79=>722,
        80=>556, 81=>722, 82=>667, 83=>556, 84=>611, 85=>722, 86=>722, 87=>944, 88=>722, 89=>722, 90=>611, 91=>333,
        92=>278, 93=>333, 94=>469, 95=>500, 96=>333, 97=>444, 98=>500, 99=>444, 100=>500, 101=>444, 102=>333, 103=>500,
        104=>500, 105=>278, 106=>278, 107=>500, 108=>278, 109=>778, 110=>500, 111=>500, 112=>500, 113=>500, 114=>333, 115=>389,
        116=>278, 117=>500, 118=>500, 119=>722, 120=>500, 121=>500, 122=>444, 123=>480, 124=>200, 125=>480, 126=>333,
        ],
    ];

    /**
     * 書き終えたページの中身（zlib で縮めたもの）。5 万件＝5,000 ページでも、描いている途中の
     * ページだけを生のまま持ち、ほかは縮めて持つ（ページの中身を全部生で持つと数百 MB になるため）。
     */
    private array $packed = [];

    public function AddPage($orientation = '', $format = '', $keepmargins = false, $tocpage = false)
    {
        $prev = $this->page;
        parent::AddPage($orientation, $format, $keepmargins, $tocpage);
        if ($prev > 0 && $prev < $this->page && ! isset($this->packed[$prev]) && isset($this->pages[$prev])) {
            $this->packed[$prev] = gzcompress($this->pages[$prev], 1);
            $this->pages[$prev] = '';
        }
    }

    protected function setPageBuffer($page, $data, $append = false)
    {
        if (isset($this->packed[$page])) {
            $this->pages[$page] = gzuncompress($this->packed[$page]);
            unset($this->packed[$page]);
        }
        parent::setPageBuffer($page, $data, $append);
    }

    protected function getPageBuffer($page)
    {
        if (isset($this->packed[$page])) {
            return gzuncompress($this->packed[$page]);
        }

        return parent::getPageBuffer($page);
    }

    /** 2 つの CID フォントを読み込み、半角の幅を reportlab にそろえる */
    public function useLabelFonts(): void
    {
        foreach (self::HALF_WIDTHS as $family => $widths) {
            $this->AddFont($family, '');
            $key = $family;
            foreach ($widths as $uni => $w) {
                $this->fonts[$key]['cw'][$uni] = $w;
            }
            // 半角カナ（U+FF61〜U+FF9F）は半角（500）
            for ($uni = 0xFF61; $uni <= 0xFF9F; $uni++) {
                $this->fonts[$key]['cw'][$uni] = 500;
            }
        }
    }

    /**
     * 左上 ($x, $y) mm から、1 モジュール $cell mm の格子に矩形を並べて黒で塗る。
     * $runs は [列, 行, 横の長さ]（行は上から数える）。
     */
    public function modulePath(float $x, float $y, float $cell, array $runs): void
    {
        if (! $runs) {
            return;
        }
        $k = $this->k;
        $s = sprintf("q 0 g %.4F 0 0 %.4F %.3F %.3F cm\n", $cell * $k, -$cell * $k, $x * $k, ($this->h - $y) * $k);
        foreach ($runs as [$c, $r, $len]) {
            $s .= "$c $r $len 1 re\n";
        }
        $this->_out($s . 'f Q');
    }
}
