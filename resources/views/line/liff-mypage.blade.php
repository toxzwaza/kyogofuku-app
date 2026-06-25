<!DOCTYPE html>
<html lang="ja">
@php
  $theme = ['my-stage' => 'indigo', 'my-points' => 'vermilion', 'mypage' => 'matcha'][$screen] ?? 'matcha';
  $heading = ['my-stage' => 'マイステージ', 'my-points' => 'マイポイント', 'mypage' => 'マイページ'][$screen] ?? 'マイページ';
@endphp
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>京呉服平田 {{ $heading }}</title>
@include('line.partials.liff-styles')
</head>
<body data-theme="{{ $theme }}">
<div class="wrap">
  <div id="loading">読み込んでいます…</div>
  <div id="content" style="display:none">
    <div class="appbar">
      <span class="seal">京呉服平田</span>
      <h1>{{ $heading }}</h1>
      <div class="rule"></div>
      <div class="sub" id="subline"></div>
    </div>
    <div id="body"></div>
  </div>
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
  const SUBLINE = { 'my-stage':'ご紹介の実績とランク', 'my-points':'ためる・つかう', 'mypage':'ご登録内容の確認' };

  const yen = (n) => Number(n||0).toLocaleString();
  const esc = (s) => String(s==null?'':s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));

  async function post(url, body) {
    const res = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type':'application/json', 'X-CSRF-TOKEN': CSRF, 'X-Requested-With':'XMLHttpRequest' },
      body: JSON.stringify(body),
    });
    return { status: res.status, data: await res.json().catch(() => ({})) };
  }

  function renderNotLinked() {
    document.getElementById('body').innerHTML =
      '<div class="card"><div class="msg warn">ご利用にはLINE連携が必要です。下のボタンから、ご予約・お客様情報と連携してください。</div>'
      + '<button class="btn btn-line" id="linkBtn">LINE連携する</button>'
      + '<p class="muted center" style="margin-top:12px">連携がお済みでない場合のみ表示されます。</p></div>';
    var b = document.getElementById('linkBtn');
    if (b) b.onclick = function () { location.href = location.pathname + '?screen=link'; };
  }

  function renderStage(d) {
    const r = d.referrals_made || {};
    const next = d.next_stage;
    const matured = d.matured_referrals_count || 0;

    let nextCard;
    if (next) {
      const pct = next.min_referrals > 0 ? Math.min(100, Math.round(matured / next.min_referrals * 100)) : 0;
      nextCard = '<div class="card"><h2>次のステージまで</h2>'
        + '<div class="next-line">あと <span class="big">'+next.remaining+'</span> 件のご成立で<br>'
        + '<b>'+(STAGE_LABEL[next.stage]||next.stage)+'</b>（還元率 '+(+next.reward_rate)+'％）</div>'
        + '<div class="progress"><i style="width:'+pct+'%"></i></div>'
        + '<div class="muted center" style="margin-top:6px">現在 '+matured+' 件 ／ '+next.min_referrals+' 件</div></div>';
    } else {
      nextCard = '<div class="card"><h2>次のステージまで</h2><p class="empty">最上位ステージです。いつもありがとうございます。</p></div>';
    }

    return ''
      + '<div class="card"><h2>あなたのステージ</h2>'
        + '<div class="crest">'+(d.stage_badge ? '<img class="badge" src="'+d.stage_badge+'" alt="'+(STAGE_LABEL[d.stage]||'')+'">' : '')
        + '<div class="name">'+(STAGE_LABEL[d.stage]||d.stage)+'</div>'
        + '<div class="meta">成立したご紹介：'+matured+' 件</div>'
        + '<div class="rate">現在の還元率 <b>'+(+d.reward_rate)+'</b>％</div></div></div>'
      + nextCard
      + '<div class="card"><h2>ご紹介の状況</h2>'
        + '<div class="kv"><span class="k">成立（特典確定）</span><span class="v">'+(r.matured||0)+' 件</span></div>'
        + '<div class="kv"><span class="k">ご成約（確定待ち）</span><span class="v">'+(r.contracted||0)+' 件</span></div>'
        + '<div class="kv"><span class="k">ご登録（成約待ち）</span><span class="v">'+(r.linked||0)+' 件</span></div></div>';
  }

  function renderPoints(d) {
    let rows = (d.ledger||[]).slice(0,30).map(l =>
      '<div class="row"><div><div>'+esc(l.note||'ポイント')+'</div><div class="d">'+esc(l.created_at)+'</div></div>'
      + '<div class="'+(l.amount>=0?'plus':'minus')+'">'+(l.amount>=0?'+':'')+yen(l.amount)+'</div></div>').join('');
    let gifts = (d.gift_cards||[]).map(g => {
      const used = g.status !== 'issued';
      return '<div class="row"><div><div>ギフトカード '+yen(g.amount)+'円</div><div class="d">'+esc(g.issued_at||'')+'</div></div>'
        + '<span class="pill'+(used?' gray':'')+'">'+(g.status==='issued'?'発行済':'取消')+'</span></div>';
    }).join('');
    let coupons = (d.coupons||[]).map(c => {
      const disc = c.discount_type === 'percent' ? ((+c.discount_value)+'％OFF') : (yen(c.discount_value)+'円OFF');
      const st = c.status === 'used' ? '使用済' : (c.usable ? '利用可' : '期限切れ');
      const usable = c.usable && c.status !== 'used';
      return '<div class="coupon'+(usable?'':' used')+'"><div><div class="nm">'+esc(c.name||'クーポン')+'</div>'
        + '<div class="disc">'+disc+'</div>'
        + '<div class="d">'+(c.valid_until?('有効期限 '+esc(c.valid_until)):'')+(c.combinable?'　併用可':'')+'</div></div>'
        + '<span class="pill'+(usable?'':' gray')+'">'+st+'</span></div>';
    }).join('');
    return ''
      + '<div class="card"><h2>ご利用可能ポイント</h2>'
        + '<div class="hero"><div class="num">'+yen(d.balance)+'<small>pt</small></div><div class="cap">POINT</div></div>'
        + '<p class="muted center" style="margin-top:12px">'+yen(d.gift_card_unit)+'円単位でギフトカードに引き換えできます（店舗にて）。</p></div>'
      + (coupons ? '<div class="card"><h2>保有クーポン</h2>'+coupons+'</div>' : '')
      + '<div class="card"><h2>ポイント履歴</h2><div class="list">'+(rows||'<div class="empty">履歴はまだありません</div>')+'</div></div>'
      + (gifts ? '<div class="card"><h2>ギフトカード</h2><div class="list">'+gifts+'</div></div>' : '');
  }

  function renderMypage(d) {
    let contracts = (d.contracts||[]).map(c =>
      '<div class="row"><div><div>'+esc(c.plan||c.kimono_type||'ご成約')+'</div><div class="d">'+esc(c.contract_date||'')+'</div></div>'
      + '<div class="v">'+yen(c.total_amount)+' 円</div></div>').join('');
    let slots = (d.photo_slots||[]).map(p =>
      '<div class="row"><div class="d">前撮り</div><div class="v">'+esc(p.shoot_date||'')+' '+esc(p.shoot_time||'')+'</div></div>').join('');
    return ''
      + '<div class="card"><div class="crest"><div class="ring"><span>'+esc((d.customer.name||'　').slice(0,1))+'</span></div>'
        + '<div class="name">'+esc(d.customer.name)+' 様</div>'
        + (d.customer.kana ? '<div class="meta">'+esc(d.customer.kana)+'</div>' : '')+'</div></div>'
      + '<div class="card"><h2>ご成約内容</h2><div class="list">'+(contracts||'<div class="empty">ご成約の登録はありません</div>')+'</div></div>'
      + '<div class="card"><h2>前撮り日</h2><div class="list">'+(slots||'<div class="empty">前撮りのご予定はありません</div>')+'</div></div>';
  }

  async function main() {
    try {
      await liff.init({ liffId: LIFF_ID });
      if (!liff.isLoggedIn()) { liff.login(); return; }
      const idToken = liff.getIDToken();
      document.getElementById('loading').style.display = 'none';
      document.getElementById('content').style.display = 'block';
      document.getElementById('subline').textContent = SUBLINE[SCREEN] || '';
      const body = document.getElementById('body');

      if (SCREEN === 'mypage') {
        const r = await post(ROUTES.mypage, { id_token: idToken });
        if (r.status === 403) return renderNotLinked();
        body.innerHTML = renderMypage(r.data);
      } else {
        const r = await post(ROUTES.points, { id_token: idToken });
        if (r.status === 403) return renderNotLinked();
        body.innerHTML = (SCREEN === 'my-stage') ? renderStage(r.data) : renderPoints(r.data);
      }
    } catch (e) {
      document.getElementById('loading').style.display = 'none';
      document.getElementById('content').style.display = 'block';
      document.getElementById('body').innerHTML = '<div class="card"><div class="msg err">エラーが発生しました。</div></div>';
    }
  }
  main();
</script>
</body>
</html>
