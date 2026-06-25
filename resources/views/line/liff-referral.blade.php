<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>京呉服平田 友達紹介</title>
<style>
  body { font-family: -apple-system, "Hiragino Sans", sans-serif; margin: 0; background: #f7f7f7; color: #222; }
  .wrap { max-width: 480px; margin: 0 auto; padding: 24px 16px; }
  .card { background: #fff; border-radius: 12px; padding: 20px; box-shadow: 0 1px 4px rgba(0,0,0,.06); margin-bottom: 16px; }
  h1 { font-size: 18px; margin: 0 0 12px; }
  .btn { display: block; width: 100%; padding: 13px; border-radius: 8px; border: none; font-size: 15px; font-weight: 600; cursor: pointer; box-sizing: border-box; }
  .btn-primary { background: #06C755; color: #fff; }
  .btn-sub { background: #fff; color: #06C755; border: 1px solid #06C755; margin-top: 10px; }
  .btn-link { background: #fff; color: #06C755; border: 1px solid #06C755; text-align: center; text-decoration: none; }
  .muted { color: #888; font-size: 13px; }
  .msg { padding: 12px; border-radius: 8px; margin-bottom: 12px; font-size: 14px; }
  .ok { background: #e6f9ee; color: #06713a; }
  .warn { background: #fff4e5; color: #8a5a00; }
  .err { background: #fdecec; color: #b3261e; }
  #loading { text-align: center; color: #888; padding: 40px 0; }
  .code { font-size: 30px; font-weight: 700; letter-spacing: 3px; text-align: center; color: #06713a; margin: 6px 0 2px; }
  .label { font-size: 12px; color: #888; text-align: center; }
  .qr { text-align: center; margin: 16px 0; }
  .qr img, .qr canvas { width: 200px; height: 200px; }
  .urlrow { display: flex; gap: 8px; margin-top: 6px; }
  .urlrow input { flex: 1; padding: 10px; border: 1px solid #ddd; border-radius: 8px; font-size: 12px; color: #444; background: #fafafa; }
  .urlrow button { padding: 0 14px; border: 1px solid #06C755; background: #fff; color: #06C755; border-radius: 8px; font-weight: 600; font-size: 13px; cursor: pointer; }
</style>
</head>
<body>
<div class="wrap">
  <div id="loading">読み込み中…</div>
  <div id="content" style="display:none">
    <div class="card">
      <h1>友達紹介</h1>
      <div id="message"></div>
      <div id="actions"></div>
    </div>
    <p class="muted" id="footnote">ご紹介で成約されると、紹介特典ポイントを進呈します。</p>
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

  function renderNotLinked() {
    showMsg('ご利用には顧客登録とLINE連携が必要です。店舗スタッフへお問い合わせください。', 'err');
  }

  // 紹介者モード：自分の紹介コード・URL・QR・共有ボタン
  function renderShare(code, shareUrl) {
    el('footnote').textContent = 'ご友人が登録・成約されると、あなたとご友人の両方に特典ポイントを進呈します。';
    showMsg('下のリンクやQRコードをご友人に共有してください。', 'ok');
    el('actions').innerHTML =
      '<div class="label">あなたの紹介コード</div>'
      + '<div class="code">'+code+'</div>'
      + '<div class="qr" id="qr"></div>'
      + '<div class="label" style="text-align:left">紹介リンク</div>'
      + '<div class="urlrow"><input id="shareInput" readonly value="'+shareUrl+'"><button id="copyBtn">コピー</button></div>'
      + '<button class="btn btn-primary" id="shareBtn" style="margin-top:16px">LINEで友だちに紹介する</button>';

    // QR
    try { new QRCode(el('qr'), { text: shareUrl, width: 200, height: 200, correctLevel: QRCode.CorrectLevel.M }); } catch (e) {}

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
      // フォールバック：コピー
      try { await navigator.clipboard.writeText(text); el('shareBtn').textContent = 'メッセージをコピーしました'; }
      catch (e) {}
    };
  }

  // 紹介者モードのエントリ
  async function runReferrer(idToken) {
    const r = await post(ROUTES.me, { id_token: idToken });
    if (r.status === 401) { showMsg('認証に失敗しました。', 'err'); return; }
    if (r.status === 403 || r.data.state === 'not_linked') { renderNotLinked(); return; }
    if (r.data.state === 'not_eligible') {
      showMsg('ご成約後に、紹介リンクをご利用いただけます。', 'warn');
      return;
    }
    if (r.data.state === 'ok') { renderShare(r.data.code, r.data.share_url); return; }
    showMsg('情報を取得できませんでした。', 'err');
  }

  // 被紹介者モード（?ref=XXXX）
  async function runReferred(idToken) {
    const check = await post(ROUTES.check, { id_token: idToken, ref: REF });
    if (check.status === 401) { showMsg('認証に失敗しました。', 'err'); return; }

    showMsg('紹介から京呉服平田の公式LINEへようこそ！下のボタンで登録を完了してください。', 'ok');
    el('actions').innerHTML = '<button class="btn btn-primary" id="linkBtn">紹介で登録する</button>'
      + '<p class="muted" style="margin-top:10px">公式アカウントの友だち追加がまだの場合は<a href="'+ADD_FRIEND_URL+'">こちら</a></p>';

    el('linkBtn').onclick = async () => {
      el('linkBtn').disabled = true;
      const r = await post(ROUTES.link, { id_token: idToken, ref: REF });
      if (r.data.state === 'linked') {
        showMsg('登録ありがとうございます。特典はご成約後にポイントで進呈いたします。', 'ok');
        el('actions').innerHTML = '';
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
