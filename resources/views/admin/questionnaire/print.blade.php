<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<title>振袖アンケート用紙{{ $mode === 'form' && ! $blank ? '（' . $customer->name . ' さま）' : '' }}</title>
<style>
  * { margin: 0; padding: 0; box-sizing: border-box; }
  html, body { background: #888; }
  body {
    font-family: "Hiragino Kaku Gothic ProN", "Hiragino Sans", "Yu Gothic", "Meiryo", sans-serif;
    font-size: 10.5pt;
    color: #222;
    line-height: 1.4;
  }
  .page {
    width: 210mm;
    height: 297mm;
    background: #fff;
    margin: 8mm auto;
    padding: 10mm 12mm;
    box-shadow: 0 2px 8px rgba(0,0,0,.35);
    position: relative;
    overflow: hidden;
  }
  @page { size: A4 portrait; margin: 0; }
  @media print {
    html, body { background: #fff; }
    .page {
      margin: 0;
      box-shadow: none;
      /* 297mmちょうどだと環境によって丸め誤差で溢れて空白ページが出るため僅かに縮める */
      height: 296.5mm;
    }
    /* 改ページは「2ページ目以降の前」にのみ入れる（末尾の改ページによる白紙ページを防ぐ） */
    .page + .page { page-break-before: always; }
  }

  /* ===== 共通パーツ ===== */
  table { border-collapse: collapse; width: 100%; }
  th, td { border: 1px solid #333; padding: 4px 6px; vertical-align: middle; }
  th {
    background: #fbe9ec;
    font-weight: 600;
    text-align: center;
  }
  .label {
    background: #fbe9ec;
    font-weight: 600;
    text-align: center;
    white-space: nowrap;
  }
  .fill { color: #999; }
  .small { font-size: 9pt; }
  .center { text-align: center; }

  /* ===== 1ページ目 ===== */
  .title-band {
    border: 2.5px solid #c94f6d;
    border-radius: 6px;
    text-align: center;
    font-size: 15pt;
    font-weight: 700;
    color: #c94f6d;
    padding: 5px 4px;
    margin-bottom: 5mm;
    letter-spacing: .5px;
  }
  .title-band .furisode { font-size: 18pt; }

  .sec { margin-bottom: 4mm; }
  .sec-title {
    display: inline-block;
    background: #c94f6d;
    color: #fff;
    font-weight: 700;
    font-size: 10pt;
    padding: 1px 10px;
    border-radius: 3px;
    margin-bottom: 1.5mm;
  }

  .kana { font-size: 8pt; color: #555; }
  .sama { text-align: right; font-size: 9pt; }

  .two-col { display: flex; gap: 4mm; }
  .two-col > div { flex: 1; }

  .choice-line { padding: 5px 6px; }

  .check-flow { display: flex; gap: 3mm; align-items: stretch; }
  .check-box {
    flex: 1;
    border: 1.5px solid #333;
    border-radius: 8px;
    text-align: center;
    padding: 2mm 1mm 6mm;
    font-weight: 600;
    font-size: 9.5pt;
  }

  /* ===== 2ページ目 ===== */
  .page2 {
    display: flex;
    flex-direction: column;
  }
  .flow-row { display: flex; gap: 2mm; align-items: stretch; margin-bottom: 3mm; }
  .flow-label {
    background: #c94f6d;
    color: #fff;
    font-weight: 700;
    writing-mode: horizontal-tb;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 3mm;
    border-radius: 3px 12px 12px 3px;
    white-space: nowrap;
    min-width: 18mm;
  }
  .flow-item {
    flex: 1;
    border: 1.5px solid #333;
    border-radius: 6px;
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: 4.5mm 1mm;
    font-weight: 600;
    font-size: 10pt;
    letter-spacing: .5px;
  }
  .photo-area {
    flex: 1;
    border: 1.5px solid #333;
    border-radius: 4px;
    padding: 2.5mm 3mm;
    margin-bottom: 4mm;
    min-height: 0;
  }
  .photo-area .caption {
    font-weight: 700;
    font-size: 10.5pt;
    letter-spacing: 1px;
    color: #555;
  }

  /* ===== スキャン印刷モード ===== */
  .scan-page {
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
  }
  .scan-page img {
    width: 100%;
    height: 100%;
    object-fit: contain;
  }
</style>
</head>
<body>

@php
    // プリフィル値（blank=1 のときはすべて空欄プレースホルダ）
    $v = fn ($value, $placeholder) => (! $blank && filled($value)) ? $value : $placeholder;
    $birth = (! $blank && $customer->birth_date)
        ? \Carbon\Carbon::parse($customer->birth_date)->format('Y年n月j日')
        : '　　　　年　　　月　　　日';
@endphp

@if ($mode === 'scan')

<!-- ============ スキャン印刷モード ============ -->
@if ($page1ScanUrl)
<div class="page scan-page">
    <img src="{{ $page1ScanUrl }}" alt="アンケート1ページ目">
</div>
@endif
@if ($composedPage2Url || $page2ScanUrl)
<div class="page scan-page">
    <img src="{{ $composedPage2Url ?? $page2ScanUrl }}" alt="アンケート2ページ目">
</div>
@endif
@if (! $page1ScanUrl && ! $composedPage2Url && ! $page2ScanUrl)
<div class="page" style="display:flex; align-items:center; justify-content:center;">
    <p>取り込み済みのスキャンがありません。</p>
</div>
@endif

@else

<!-- ============ 1ページ目：お客様記入欄 ============ -->
<div class="page">

  <div class="title-band">あなたにピッタリの<span class="furisode">振袖</span>をお探し致します！あなたの事を教えてください</div>

  <!-- 基本情報 -->
  <div class="sec">
    <table>
      <tr>
        <td class="label" style="width:16%">ご来店日</td>
        <td style="width:36%">20　　年　　　月　　　日</td>
        <td class="label" style="width:18%">成人式の年</td>
        <td>（20{{ ! $blank && $customer->coming_of_age_year ? mb_substr((string) $customer->coming_of_age_year, 2) : '　　　' }}年）</td>
      </tr>
      <tr>
        <td class="label">フリガナ</td>
        <td class="kana">{{ $v($customer->kana, '') }}&nbsp;</td>
        <td class="label">生年月日</td>
        <td>{{ $birth }}</td>
      </tr>
      <tr>
        <td class="label">お名前</td>
        <td style="height:11mm">{{ $v($customer->name, '') }}<span class="sama" style="float:right; margin-top:6mm">さま</span></td>
        <td class="label">お母様の<br>お名前</td>
        <td>{{ $v($customer->guardian_name, '') }}<span class="sama" style="float:right; margin-top:4mm">さま</span></td>
      </tr>
      <tr>
        <td class="label" rowspan="2">ご住所</td>
        <td rowspan="2">〒{{ $v($customer->postal_code, '　　　－') }}<br>{{ $v($customer->address, '') }}<br></td>
        <td class="label">携帯番号</td>
        <td>&nbsp;</td>
      </tr>
      <tr>
        <td class="label">身長</td>
        <td>　　　　　　　㎝</td>
      </tr>
      <tr>
        <td class="label" rowspan="2">お電話</td>
        <td><span class="label" style="border:none; padding:0 8px 0 0; background:none">ご自宅</span>&nbsp;</td>
        <td class="label" rowspan="2">足のサイズ</td>
        <td rowspan="2">　　　　　　　㎝</td>
      </tr>
      <tr>
        <td><span class="label" style="border:none; padding:0 8px 0 0; background:none">携　帯</span>{{ $v($customer->phone_number, '') }}&nbsp;</td>
      </tr>
    </table>
  </div>

  <!-- ご職業 -->
  <div class="sec">
    <table>
      <tr>
        <td class="choice-line">
          大学生 ・ 短大生 ・ 専門学校生 ・ 高校生（学校名：{{ $v($customer->school_name, '　　　　　　　　　　　　') }}）<br>
          アルバイト ・ お勤め ・ その他（　　　　　　　　　　　　　）
        </td>
      </tr>
    </table>
  </div>

  <!-- きっかけ／ご姉妹 -->
  <div class="sec two-col">
    <div>
      <table>
        <tr>
          <td class="label" rowspan="6" style="width:26%">当店を<br>知った<br>きっかけは？</td>
          <td>ＤＭはがき ・ ホームページ</td>
        </tr>
        <tr><td>電話 ・ チラシ ・ 通りがかり</td></tr>
        <tr><td>インスタグラム ・ タウン情報誌</td></tr>
        <tr><td>美容室ご紹介（　　　　　　　　）</td></tr>
        <tr><td>お友達ご紹介（　　　　　　　　）</td></tr>
        <tr><td>その他（　　　　　　　　　　　）</td></tr>
      </table>
    </div>
    <div>
      <table>
        <tr>
          <td class="label" rowspan="6" style="width:26%">ご姉妹は<br>いらっしゃ<br>いますか？</td>
          <td class="kana">フリガナ</td>
        </tr>
        <tr><td style="height:8mm"><span class="sama" style="float:right; margin-top:3mm">さま</span></td></tr>
        <tr><td>生年月日　　　　年　　月　　日生まれ</td></tr>
        <tr><td class="kana">フリガナ</td></tr>
        <tr><td style="height:8mm"><span class="sama" style="float:right; margin-top:3mm">さま</span></td></tr>
        <tr><td>生年月日　　　　年　　月　　日生まれ</td></tr>
      </table>
    </div>
  </div>

  <!-- 学生向け -->
  <div class="sec two-col">
    <div>
      <div class="sec-title">★大学・短大・専門学生の方へ★</div>
      <table>
        <tr>
          <td class="label" style="width:40%">卒業予定年</td>
          <td>　　　　　年3月</td>
        </tr>
        <tr>
          <td class="label">卒業式に袴を<br>着たいですか？</td>
          <td class="center">はい　／　いいえ</td>
        </tr>
      </table>
    </div>
    <div>
      <div class="sec-title">★高校生の方へ★</div>
      <table>
        <tr>
          <td class="label">今後の進路予定をお聞かせください</td>
        </tr>
        <tr>
          <td class="center" style="height:9mm">大学 ・ 短大 ・ 専門学校 ・ お勤め</td>
        </tr>
      </table>
    </div>
  </div>

  <!-- 好み -->
  <div class="sec">
    <table>
      <tr>
        <td class="label" style="width:20%">好きな色</td>
        <td>赤 ・ ピンク ・ 黄 ・ 緑 ・ 青 ・ 紫 ・ 茶 ・ 黒 ・ 白 ・ その他（　　　　　）</td>
      </tr>
      <tr>
        <td class="label">お好きな柄</td>
        <td>かわいい ・ カッコイイ ・ ゴージャス ・ 古典 ・ シンプル ・ 柄多め ・ その他（　　　　）</td>
      </tr>
      <tr>
        <td class="label">好きな<br>タレント・モデル<br>youtuber など</td>
        <td style="height:16mm">&nbsp;</td>
      </tr>
    </table>
  </div>

  <!-- 担当・チェック -->
  <div class="sec two-col">
    <div style="flex:1.2">
      <table>
        <tr>
          <td class="label" style="width:45%">お客様担当名</td>
          <td style="height:10mm">{{ $v($customer->staff_name, '') }}&nbsp;</td>
        </tr>
      </table>
    </div>
    <div style="flex:1.8">
      <div class="check-flow">
        <div class="check-box">チェック①</div>
        <div class="check-box">チェック②</div>
        <div class="check-box">最終チェック</div>
      </div>
    </div>
  </div>

  <!-- 前撮り・成人式当日 -->
  <div class="sec">
    <table>
      <tr>
        <td class="label" style="width:16%">前撮り日</td>
        <td style="width:34%" class="center">　　　年　　　月　　　日（　　　）</td>
        <td class="label" style="width:16%">成人式当日</td>
        <td class="center">お支度会場</td>
      </tr>
      <tr>
        <td class="label">時　間</td>
        <td class="center">　　　　：　　　　</td>
        <td class="label">時　間</td>
        <td class="center">　　　　：　　　　</td>
      </tr>
    </table>
  </div>

</div>

<!-- ============ 2ページ目：店舗管理欄 ============ -->
<div class="page page2">

  <div class="title-band" style="font-size:12pt; padding:3px 4px; margin-bottom:4mm">店舗管理欄（社内用）</div>

  <!-- 各店フロー -->
  <div class="flow-row">
    <div class="flow-label">各　店</div>
    <div class="flow-item">スプレッドＳ</div>
    <div class="flow-item" style="flex:1.6">管理表</div>
    <div class="flow-item">タグ</div>
    <div class="flow-item">発注書</div>
    <div class="flow-item">伝コピー</div>
    <div class="flow-item" style="flex:1.6">日紋</div>
    <div class="flow-item">受取</div>
  </div>

  <!-- 倉庫フロー -->
  <div class="flow-row">
    <div class="flow-label">倉　庫</div>
    <div class="flow-item">受取</div>
    <div class="flow-item" style="flex:1.6">発注</div>
    <div class="flow-item">現品☑</div>
    <div class="flow-item">発注品IN</div>
    <div class="flow-item">振袖IN</div>
    <div class="flow-item" style="flex:1.6">各店渡し</div>
    <div class="flow-item" style="visibility:hidden">&nbsp;</div>
  </div>

  <!-- 写真添付欄 -->
  <div class="photo-area">
    <span class="caption">※写真添付欄</span>
  </div>

  <!-- 小物管理表 -->
  <table>
    <tr>
      <th style="width:12%">種　類</th>
      <th>刺繍衿</th>
      <th>重ね衿</th>
      <th>帯揚げ</th>
      <th>帯〆</th>
      <th>草履バック</th>
      <th>ショール</th>
      <th>他</th>
    </tr>
    <tr>
      <td class="label">発　注</td>
      <td class="center" style="height:11mm">／</td>
      <td class="center">／</td>
      <td class="center">／</td>
      <td class="center">／</td>
      <td class="center">／</td>
      <td class="center">／</td>
      <td class="center">／</td>
    </tr>
    <tr>
      <td class="label">入荷日</td>
      <td class="center small" style="height:13mm; line-height:1.7">レ・手・購<br>／　・　現</td>
      <td class="center small" style="line-height:1.7">レ・手・購<br>／　・　現</td>
      <td class="center small" style="line-height:1.7">レ・手・購<br>／　・　現</td>
      <td class="center small" style="line-height:1.7">レ・手・購<br>／　・　現</td>
      <td class="center small" style="line-height:1.7">レ・手・購<br>／　・　現</td>
      <td class="center small" style="line-height:1.7">レ・手・購<br>／　・　現</td>
      <td class="center small" style="line-height:1.7">レ・手・購<br>／　・　現</td>
    </tr>
  </table>

</div>

@endif

<script>
  // 画像読み込み完了後に印刷ダイアログを開く
  window.addEventListener('load', function () {
    setTimeout(function () { window.print(); }, 300);
  });
</script>

</body>
</html>
