{{-- 友達紹介系LIFF 共通デザインシステム（和モダン）。data-theme で画面別アクセント色を切替 --}}
<style>
  :root{
    --bg:#f6f2ea; --ink:#3a352e; --ink-soft:#7d756a; --line:#e7ddcb;
    --card:#fffdf9; --shadow:0 6px 20px rgba(60,48,28,.08);
    --accent:#b08d57; --accent-deep:#8a6d2f; --accent-soft:#f3e9d2; --wave:#e7d9bf;
    --mincho:"Hiragino Mincho ProN","Yu Mincho",YuMincho,"Noto Serif JP",serif;
    --gothic:"Hiragino Sans","Hiragino Kaku Gothic ProN","Noto Sans JP",sans-serif;
  }
  [data-theme=gold]{ --accent:#b08d57; --accent-deep:#8a6d2f; --accent-soft:#f4ecdb; --wave:#e7d9bf; }
  [data-theme=indigo]{ --accent:#4a679e; --accent-deep:#34406e; --accent-soft:#e9edf6; --wave:#d6deef; }
  [data-theme=vermilion]{ --accent:#b3645f; --accent-deep:#8c3b36; --accent-soft:#f7e9e7; --wave:#eed7d4; }
  [data-theme=matcha]{ --accent:#5a9070; --accent-deep:#2f6b47; --accent-soft:#e9f1ea; --wave:#d6e7da; }

  *{ box-sizing:border-box; -webkit-tap-highlight-color:transparent; }
  body{ margin:0; background:var(--bg); color:var(--ink); font-family:var(--gothic); line-height:1.6;
    -webkit-font-smoothing:antialiased; }
  .wrap{ max-width:480px; margin:0 auto; padding:0 16px 40px; }

  /* ===== 和の見出しバー（青海波＋明朝＋金罫） ===== */
  .appbar{ position:relative; margin:0 -16px 18px; padding:30px 16px 22px; overflow:hidden;
    background:
      radial-gradient(circle at 100% 150%, var(--accent-soft) 24%, var(--card) 25%, var(--card) 28%, var(--accent-soft) 29%, var(--accent-soft) 36%, var(--card) 37%, var(--card) 40%, transparent 40%),
      radial-gradient(circle at 0 150%, var(--accent-soft) 24%, var(--card) 25%, var(--card) 28%, var(--accent-soft) 29%, var(--accent-soft) 36%, var(--card) 37%, var(--card) 40%, transparent 40%),
      radial-gradient(circle at 50% 100%, var(--card) 10%, var(--accent-soft) 11%, var(--accent-soft) 23%, var(--card) 24%, var(--card) 30%, var(--accent-soft) 31%, var(--accent-soft) 43%, var(--card) 44%, var(--card) 50%, transparent 50%),
      linear-gradient(180deg, var(--card), var(--bg));
    background-size:54px 27px,54px 27px,54px 27px,100% 100%;
    border-bottom:1px solid var(--line); text-align:center; }
  .appbar .seal{ display:inline-block; font-family:var(--mincho); font-size:12px; letter-spacing:2px;
    color:#fff; background:var(--accent-deep); padding:3px 12px; border-radius:2px; }
  .appbar h1{ font-family:var(--mincho); font-size:23px; font-weight:600; letter-spacing:4px;
    margin:12px 0 6px; color:var(--accent-deep); }
  .appbar .rule{ width:46px; height:2px; background:var(--accent); margin:0 auto; border-radius:2px; }
  .appbar .sub{ font-size:12px; color:var(--ink-soft); margin-top:8px; letter-spacing:.5px; }

  /* ===== カード ===== */
  .card{ position:relative; background:var(--card); border:1px solid var(--line); border-radius:14px;
    padding:20px 18px; box-shadow:var(--shadow); margin-bottom:14px; }
  .card::before{ content:""; position:absolute; top:0; left:18px; right:18px; height:3px; border-radius:0 0 3px 3px;
    background:linear-gradient(90deg, var(--accent), var(--accent-soft)); }
  .card h2{ font-family:var(--mincho); font-size:15px; letter-spacing:1px; margin:2px 0 14px; color:var(--accent-deep);
    display:flex; align-items:center; gap:8px; }
  .card h2::before{ content:""; width:6px; height:6px; background:var(--accent); transform:rotate(45deg); }

  /* ===== 汎用 ===== */
  .muted{ color:var(--ink-soft); font-size:12.5px; }
  .center{ text-align:center; }
  .msg{ padding:11px 13px; border-radius:10px; font-size:13.5px; margin-bottom:14px; }
  .msg.ok{ background:var(--accent-soft); color:var(--accent-deep); }
  .msg.warn{ background:#fff4e2; color:#8a5a00; }
  .msg.err{ background:#fdeceb; color:#b3261e; }
  #loading{ text-align:center; color:var(--ink-soft); padding:64px 0; font-family:var(--mincho); letter-spacing:2px; }

  /* ステージ章 */
  .crest{ text-align:center; }
  .crest .ring{ display:inline-flex; align-items:center; justify-content:center; width:96px; height:96px; border-radius:50%;
    background:radial-gradient(circle at 50% 35%, #fff, var(--accent-soft)); border:2px solid var(--accent);
    box-shadow:inset 0 0 0 4px #fff, var(--shadow); }
  .crest .ring span{ font-family:var(--mincho); font-size:26px; font-weight:600; color:var(--accent-deep); }
  .crest .badge{ width:132px; height:132px; object-fit:contain; filter:drop-shadow(0 5px 12px rgba(60,48,28,.22)); }
  .crest .name{ font-family:var(--mincho); font-size:19px; letter-spacing:3px; color:var(--accent-deep); margin-top:12px; }
  .crest .meta{ font-size:12.5px; color:var(--ink-soft); margin-top:4px; }

  /* 大きな数値（ポイント） */
  .hero{ text-align:center; padding:6px 0 2px; }
  .hero .num{ font-family:var(--mincho); font-size:44px; font-weight:600; color:var(--accent-deep); line-height:1.1; }
  .hero .num small{ font-size:16px; margin-left:4px; color:var(--accent); }
  .hero .cap{ font-size:12px; color:var(--ink-soft); letter-spacing:1px; }

  /* 進捗バー */
  .progress{ height:8px; border-radius:6px; background:var(--accent-soft); overflow:hidden; margin:10px 0 6px; }
  .progress > i{ display:block; height:100%; background:linear-gradient(90deg,var(--accent),var(--accent-deep)); border-radius:6px; }

  /* 紹介コード */
  .codebox{ text-align:center; padding:14px; border:1px dashed var(--accent); border-radius:12px; background:var(--accent-soft); }
  .codebox .cap{ font-size:11px; letter-spacing:2px; color:var(--accent-deep); }
  .codebox .code{ font-family:var(--mincho); font-size:30px; font-weight:700; letter-spacing:6px; color:var(--accent-deep); margin-top:2px; }
  .qr{ display:flex; justify-content:center; padding:14px; }
  .qr img,.qr canvas{ width:188px; height:188px; display:block; padding:10px; background:#fff; border:1px solid var(--line); border-radius:10px; }
  .urlrow{ display:flex; gap:8px; margin-top:8px; }
  .urlrow input{ flex:1; min-width:0; padding:11px; border:1px solid var(--line); border-radius:10px; font-size:12px; color:var(--ink-soft); background:#fbf8f1; }

  /* ボタン */
  .btn{ display:flex; align-items:center; justify-content:center; gap:7px; width:100%; padding:14px; border-radius:12px;
    border:none; font-size:15px; font-weight:700; font-family:var(--gothic); cursor:pointer; letter-spacing:1px; }
  .btn-primary{ background:linear-gradient(180deg,var(--accent),var(--accent-deep)); color:#fff; box-shadow:0 4px 12px rgba(138,109,47,.25); }
  .btn-line{ background:#06C755; color:#fff; }
  .btn-ghost{ background:#fff; color:var(--accent-deep); border:1px solid var(--accent); }
  .btn-unlink{ background:#fff; color:#a14; border:1px solid #e0b6b6; font-weight:600; }
  .btn + .btn{ margin-top:10px; }
  .copybtn{ padding:0 16px; border:1px solid var(--accent); background:#fff; color:var(--accent-deep); border-radius:10px; font-weight:700; font-size:13px; }

  /* key-value / 一覧 */
  .kv{ display:flex; justify-content:space-between; gap:12px; padding:11px 2px; border-bottom:1px solid var(--line); font-size:14px; }
  .kv:last-child{ border-bottom:none; }
  .kv .k{ color:var(--ink-soft); }
  .kv .v{ font-weight:600; text-align:right; }
  .list{ font-size:13.5px; }
  .list .row{ display:flex; justify-content:space-between; gap:10px; padding:10px 2px; border-bottom:1px dotted var(--line); }
  .list .row:last-child{ border-bottom:none; }
  .list .row .d{ color:var(--ink-soft); font-size:12px; }
  .plus{ color:var(--accent-deep); font-weight:700; }
  .minus{ color:#b06; font-weight:700; }
  .pill{ display:inline-block; font-size:11px; padding:2px 9px; border-radius:20px; background:var(--accent-soft); color:var(--accent-deep); font-weight:700; }
  .pill.gray{ background:#eee; color:#888; }
  .empty{ text-align:center; color:var(--ink-soft); font-size:13px; padding:14px 0; font-family:var(--mincho); letter-spacing:1px; }

  /* クーポン（チケット風） */
  .coupon{ position:relative; display:flex; align-items:center; justify-content:space-between; gap:12px;
    border:1px solid var(--line); border-left:4px solid var(--accent); border-radius:10px; padding:13px 14px; margin-bottom:10px;
    background:linear-gradient(90deg, var(--accent-soft), var(--card) 70%); }
  .coupon:last-child{ margin-bottom:0; }
  .coupon.used{ opacity:.5; filter:grayscale(.35); }
  .coupon .nm{ font-weight:600; font-size:13.5px; }
  .coupon .disc{ font-family:var(--mincho); font-size:19px; font-weight:700; color:var(--accent-deep); margin:1px 0; }
  .coupon .d{ color:var(--ink-soft); font-size:11.5px; }

  /* フォーム入力 */
  label.fld{ display:block; margin:16px 0 6px; font-size:13px; font-weight:700; color:var(--accent-deep); font-family:var(--mincho); letter-spacing:1px; }
  label.fld .opt{ font-family:var(--gothic); font-weight:400; font-size:11px; color:var(--ink-soft); margin-left:6px; }
  .input{ width:100%; box-sizing:border-box; padding:13px 14px; font-size:16px; border:1px solid var(--line); border-radius:10px; background:#fbf8f1; color:var(--ink); }
  .input:focus{ outline:none; border-color:var(--accent); background:#fff; }
  .help{ font-size:11.5px; color:var(--ink-soft); margin:5px 0 0; line-height:1.5; }
  .lead{ font-size:13.5px; color:var(--ink); line-height:1.7; margin:0 0 4px; }
  #status{ margin-top:14px; font-size:13.5px; white-space:pre-wrap; word-break:break-word; text-align:center; }
  #status.err{ color:#b3261e; }
  #status.ok{ color:var(--accent-deep); font-weight:700; }
  #status.hint{ color:var(--ink-soft); }

  /* 還元率バッジ */
  .rate{ display:inline-flex; align-items:baseline; gap:3px; margin-top:8px; padding:4px 12px; border-radius:20px;
    background:var(--accent-soft); color:var(--accent-deep); font-weight:700; }
  .rate b{ font-family:var(--mincho); font-size:19px; }
  .next-line{ text-align:center; margin:2px 0 10px; }
  .next-line b{ color:var(--accent-deep); }
  .next-line .big{ font-family:var(--mincho); font-size:22px; }
</style>
