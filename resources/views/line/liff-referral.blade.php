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
  .btn { display: block; width: 100%; padding: 12px; border-radius: 8px; border: none; font-size: 15px; font-weight: 600; cursor: pointer; }
  .btn-primary { background: #06C755; color: #fff; }
  .btn-link { background: #fff; color: #06C755; border: 1px solid #06C755; text-align: center; text-decoration: none; }
  .muted { color: #888; font-size: 13px; }
  .msg { padding: 12px; border-radius: 8px; margin-bottom: 12px; font-size: 14px; }
  .ok { background: #e6f9ee; color: #06713a; }
  .warn { background: #fff4e5; color: #8a5a00; }
  .err { background: #fdecec; color: #b3261e; }
  #loading { text-align: center; color: #888; padding: 40px 0; }
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
    <p class="muted">ご紹介で成約されると、紹介特典ポイントを進呈します。</p>
  </div>
</div>

<script src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
<script>
  const LIFF_ID = @json($liffId);
  const REF = @json($ref);
  const ADD_FRIEND_URL = @json($addFriendUrl);
  const CSRF = @json(csrf_token());

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

  async function main() {
    try {
      await liff.init({ liffId: LIFF_ID });
      if (!liff.isLoggedIn()) { liff.login(); return; }
      const idToken = liff.getIDToken();

      el('loading').style.display = 'none';
      el('content').style.display = 'block';

      if (!REF) {
        showMsg('紹介コードが指定されていません。', 'warn');
        return;
      }

      // 状態確認
      const check = await post(@json(route('line.liff.referral.check')), { id_token: idToken, ref: REF });
      if (check.status === 401) { showMsg('認証に失敗しました。', 'err'); return; }

      showMsg('紹介から京呉服平田の公式LINEへようこそ！下のボタンで登録を完了してください。', 'ok');
      el('actions').innerHTML = '<button class="btn btn-primary" id="linkBtn">紹介で登録する</button>'
        + '<p class="muted" style="margin-top:10px">公式アカウントの友だち追加がまだの場合は<a href="'+ADD_FRIEND_URL+'">こちら</a></p>';

      el('linkBtn').onclick = async () => {
        el('linkBtn').disabled = true;
        const r = await post(@json(route('line.liff.referral.link')), { id_token: idToken, ref: REF });
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
