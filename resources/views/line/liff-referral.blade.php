<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>京呉服平田 友達紹介</title>
@include('line.partials.liff-styles')
</head>
<body data-theme="gold">
<div class="wrap">
  <div id="loading">読み込んでいます…</div>
  <div id="content" style="display:none">
    <div class="appbar">
      <span class="seal">京呉服平田</span>
      <h1>友達紹介</h1>
      <div class="rule"></div>
      <div class="sub" id="subline">大切な方をご紹介ください</div>
    </div>
    <div id="events-block"></div>
    <div class="card">
      <div id="message"></div>
      <div id="actions"></div>
    </div>
    <div id="referrer-block"></div>
    <p class="muted center" id="footnote">ご紹介で成約されると、紹介特典ポイントを進呈します。</p>
  </div>
</div>

<script src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
  const LIFF_ID = @json($liffId);
  const REF = @json($ref);
  const ADD_FRIEND_URL = @json($addFriendUrl);
  const CSRF = @json(csrf_token());
  const ROUTES = {
    me: @json(route('line.liff.referral.me')),
    check: @json(route('line.liff.referral.check')),
    link: @json(route('line.liff.referral.link')),
  };

  const el = (id) => document.getElementById(id);
  function showMsg(html, cls) { el('message').innerHTML = '<div class="msg '+cls+'">'+html+'</div>'; }

  async function post(url, body) {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With': 'XMLHttpRequest' },
      body: JSON.stringify(body),
    });
    return { status: res.status, data: await res.json().catch(() => ({})) };
  }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  }

  function renderNotLinked() {
    showMsg('ご利用には顧客登録とLINE連携が必要です。店舗スタッフへお問い合わせください。', 'err');
  }

  // 紹介者の店舗で開催中のイベント（サムネイル縦並び。タップで予約ページへ）
  function renderEventsBlock(events) {
    if (!events || !events.length) { el('events-block').innerHTML = ''; return; }
    const items = events.map((e) => {
      const inner = e.thumbnail_url
        ? '<img src="' + esc(e.thumbnail_url) + '" alt="' + esc(e.title) + '" loading="lazy">'
        : '<div class="event-card-title">' + esc(e.title) + '</div>';
      return '<a class="event-card" href="' + esc(e.reserve_url) + '">' + inner + '</a>';
    }).join('');
    el('events-block').innerHTML =
      '<div class="card"><h2>開催中のイベント</h2><div class="event-list">' + items + '</div>'
      + '<p class="muted center" style="margin-top:10px">画像をタップすると予約ページへ移動します。</p></div>';
  }

  // 紹介者ブロック：誰から紹介を受けたかを常に表示（紹介者がいなければ空欄）
  function renderReferrerBlock(referrer) {
    const val = (referrer && referrer.name)
      ? '[' + esc(referrer.id) + '] ' + esc(referrer.name)
      : '―';
    el('referrer-block').innerHTML =
      '<div class="card"><div class="kv"><span class="k">ご紹介者</span><span class="v">' + val + '</span></div></div>';
  }

  // 紹介者モード：自分の紹介コード・URL・QR・共有ボタン
  function renderShare(code, shareUrl) {
    el('subline').textContent = 'あなた専用の紹介リンク';
    el('footnote').textContent = 'ご友人が登録・成約されると、あなたとご友人の両方に特典ポイントを進呈します。';
    showMsg('下のリンクまたはQRコードをご友人に共有してください。', 'ok');
    el('actions').innerHTML =
      '<div class="codebox"><div class="cap">あなたの紹介コード</div><div class="code">'+code+'</div></div>'
      + '<div class="qr" id="qr"></div>'
      + '<div class="muted">紹介リンク</div>'
      + '<div class="urlrow"><input id="shareInput" readonly value="'+shareUrl+'"><button class="copybtn" id="copyBtn">コピー</button></div>'
      + '<button class="btn btn-line" id="shareBtn" style="margin-top:18px">LINEで友だちに紹介する</button>';

    try { new QRCode(el('qr'), { text: shareUrl, width: 188, height: 188, correctLevel: QRCode.CorrectLevel.M }); } catch (e) {}

    el('copyBtn').onclick = async () => {
      try { await navigator.clipboard.writeText(shareUrl); }
      catch (e) { const i = el('shareInput'); i.select(); document.execCommand('copy'); }
      el('copyBtn').textContent = 'コピー済';
      setTimeout(() => { el('copyBtn').textContent = 'コピー'; }, 1500);
    };

    el('shareBtn').onclick = async () => {
      const text = '京呉服平田の公式LINEのご案内です✨\nこちらから登録・ご成約で特典ポイントがもらえます。\n' + shareUrl;
      if (liff.isApiAvailable('shareTargetPicker')) {
        try { await liff.shareTargetPicker([{ type: 'text', text: text }]); return; }
        catch (e) {}
      }
      try { await navigator.clipboard.writeText(text); el('shareBtn').textContent = 'メッセージをコピーしました'; }
      catch (e) {}
    };
  }

  async function runReferrer(idToken) {
    const r = await post(ROUTES.me, { id_token: idToken });
    if (r.status === 401) { showMsg('認証に失敗しました。', 'err'); return; }
    // 顧客登録・成約の有無に関わらず、誰から紹介されたかを常に表示
    renderReferrerBlock(r.data.referrer);
    // イベント予約経由でLINE連携済みだが顧客（成約）未紐付けの場合
    if (r.data.state === 'not_contracted') {
      el('subline').textContent = 'ご成約後にご利用いただけます';
      showMsg('この機能をご利用いただくには成約が必要です。', 'warn');
      return;
    }
    if (r.status === 403 || r.data.state === 'not_linked') {
      renderEventsBlock(r.data.events);
      renderNotLinked();
      return;
    }
    if (r.data.state === 'not_eligible') {
      el('subline').textContent = 'ご成約後にご利用いただけます';
      showMsg('ご成約後に、紹介リンクをご利用いただけます。', 'warn');
      return;
    }
    if (r.data.state === 'ok') { renderShare(r.data.code, r.data.share_url); return; }
    showMsg('情報を取得できませんでした。', 'err');
  }

  async function runReferred(idToken) {
    el('subline').textContent = 'ご紹介ありがとうございます';
    const check = await post(ROUTES.check, { id_token: idToken, ref: REF });
    if (check.status === 401) { showMsg('認証に失敗しました。', 'err'); return; }

    // 紹介者の店舗で開催中のイベントを登録ブロックの上に表示
    renderEventsBlock(check.data.events);

    showMsg('紹介から京呉服平田の公式LINEへようこそ。下のボタンで登録を完了してください。', 'ok');
    // 初期表示は「紹介で登録する」のみ。友だち追加ボタンは登録成立後に表示する。
    el('actions').innerHTML = '<button class="btn btn-primary" id="linkBtn">紹介で登録する</button>';

    el('linkBtn').onclick = async () => {
      el('linkBtn').disabled = true;
      const r = await post(ROUTES.link, { id_token: idToken, ref: REF });
      if (r.data.state === 'linked') {
        showMsg('登録ありがとうございます。特典はご成約後にポイントで進呈いたします。', 'ok');
        // 登録が成立したら、公式アカウント友だち追加ボタンを表示する
        el('actions').innerHTML = ADD_FRIEND_URL
          ? '<a class="btn btn-line" id="addFriendBtn" href="'+ADD_FRIEND_URL+'" style="text-decoration:none">公式アカウントを友だち追加する</a>'
            + '<p class="muted center" style="margin-top:12px">続けて、公式アカウントの友だち追加をお願いします（未追加の場合は特典が正しく進呈されません）。</p>'
          : '';
      } else if (r.data.state === 'rejected') {
        showMsg('すでにご利用中、もしくは紹介条件を満たさないため特典は適用されません。', 'warn');
        el('actions').innerHTML = '';
      } else {
        showMsg('登録済みです。', 'ok');
        el('actions').innerHTML = '';
      }
    };
  }

  async function main() {
    try {
      await liff.init({ liffId: LIFF_ID });
      if (!liff.isLoggedIn()) { liff.login(); return; }
      const idToken = liff.getIDToken();
      el('loading').style.display = 'none';
      el('content').style.display = 'block';
      if (REF) { await runReferred(idToken); }
      else { await runReferrer(idToken); }
    } catch (e) {
      el('loading').style.display = 'none';
      el('content').style.display = 'block';
      showMsg('エラーが発生しました。時間をおいて再度お試しください。', 'err');
    }
  }
  main();
</script>
</body>
</html>
