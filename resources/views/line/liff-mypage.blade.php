<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>京呉服平田 マイページ</title>
<style>
  body { font-family: -apple-system, "Hiragino Sans", sans-serif; margin: 0; background: #f7f7f7; color: #222; }
  .wrap { max-width: 480px; margin: 0 auto; padding: 20px 16px; }
  .card { background: #fff; border-radius: 12px; padding: 18px; box-shadow: 0 1px 4px rgba(0,0,0,.06); margin-bottom: 14px; }
  h1 { font-size: 17px; margin: 0 0 12px; }
  h2 { font-size: 14px; margin: 0 0 8px; color: #555; }
  .big { font-size: 28px; font-weight: 700; color: #06C755; }
  .stage { display: inline-block; padding: 4px 12px; border-radius: 16px; font-weight: 700; }
  .b-bronze { background:#fde7d2; color:#8a4b00; } .b-silver { background:#e5e7eb; color:#374151; }
  .b-gold { background:#fdf0c8; color:#8a6d00; } .b-platinum { background:#e0e7ff; color:#3730a3; }
  table { width: 100%; border-collapse: collapse; font-size: 13px; }
  th, td { padding: 6px 4px; border-bottom: 1px solid #eee; text-align: left; }
  .muted { color: #888; font-size: 13px; }
  #loading { text-align:center; color:#888; padding:40px 0; }
  .err { background:#fdecec; color:#b3261e; padding:12px; border-radius:8px; }
</style>
</head>
<body>
<div class="wrap">
  <div id="loading">読み込み中…</div>
  <div id="content"></div>
</div>

<script src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
<script>
  const LIFF_ID = @json($liffId);
  const SCREEN = @json($screen);
  const CSRF = @json(csrf_token());
  const ROUTES = {
    points: @json(route('line.liff.my-points.data')),
    mypage: @json(route('line.liff.mypage.data')),
  };
  const STAGE_LABEL = { bronze:'ブロンズ', silver:'シルバー', gold:'ゴールド', platinum:'プラチナ' };

  async function post(url, body) {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With':'XMLHttpRequest' },
      body: JSON.stringify(body),
    });
    return { status: res.status, data: await res.json().catch(() => ({})) };
  }
  const yen = (n) => Number(n||0).toLocaleString();

  function renderNotLinked() {
    document.getElementById('content').innerHTML =
      '<div class="card"><div class="err">ご利用には顧客登録とLINE連携が必要です。店舗スタッフへお問い合わせください。</div></div>';
  }
  function renderStage(d) {
    return '<div class="card"><h1>マイステージ</h1>'
      + '<span class="stage b-'+d.stage+'">'+(STAGE_LABEL[d.stage]||d.stage)+'</span>'
      + '<p class="muted" style="margin-top:10px">成立した紹介：'+d.matured_referrals_count+' 件</p></div>';
  }
  function renderPoints(d) {
    let rows = (d.ledger||[]).slice(0,20).map(l =>
      '<tr><td>'+l.created_at+'</td><td>'+l.note+'</td><td style="text-align:right">'+(l.amount>=0?'+':'')+yen(l.amount)+'</td></tr>').join('');
    return '<div class="card"><h1>マイポイント</h1>'
      + '<div class="big">'+yen(d.balance)+' pt</div>'
      + '<p class="muted">'+yen(d.gift_card_unit)+'円単位でギフトカードに引き換えできます（店舗にて）。</p></div>'
      + '<div class="card"><h2>ポイント履歴</h2><table>'+(rows||'<tr><td class="muted">履歴はありません</td></tr>')+'</table></div>';
  }
  function renderMypage(d) {
    let contracts = (d.contracts||[]).map(c =>
      '<tr><td>'+(c.contract_date||'')+'</td><td>'+(c.plan||c.kimono_type||'')+'</td><td style="text-align:right">'+yen(c.total_amount)+'円</td></tr>').join('');
    let slots = (d.photo_slots||[]).map(p =>
      '<tr><td>'+(p.shoot_date||'')+'</td><td>'+(p.shoot_time||'')+'</td></tr>').join('');
    return '<div class="card"><h1>マイページ</h1><p>'+d.customer.name+' 様</p></div>'
      + '<div class="card"><h2>ご成約内容</h2><table>'+(contracts||'<tr><td class="muted">成約情報はありません</td></tr>')+'</table></div>'
      + '<div class="card"><h2>前撮り日</h2><table>'+(slots||'<tr><td class="muted">前撮りの予定はありません</td></tr>')+'</table></div>';
  }

  async function main() {
    try {
      await liff.init({ liffId: LIFF_ID });
      if (!liff.isLoggedIn()) { liff.login(); return; }
      const idToken = liff.getIDToken();
      document.getElementById('loading').style.display = 'none';
      const content = document.getElementById('content');

      if (SCREEN === 'mypage') {
        const r = await post(ROUTES.mypage, { id_token: idToken });
        if (r.status === 403) return renderNotLinked();
        content.innerHTML = renderMypage(r.data);
      } else {
        const r = await post(ROUTES.points, { id_token: idToken });
        if (r.status === 403) return renderNotLinked();
        content.innerHTML = (SCREEN === 'my-stage') ? renderStage(r.data) : renderPoints(r.data);
      }
    } catch (e) {
      document.getElementById('loading').style.display = 'none';
      document.getElementById('content').innerHTML = '<div class="card"><div class="err">エラーが発生しました。</div></div>';
    }
  }
  main();
</script>
</body>
</html>
