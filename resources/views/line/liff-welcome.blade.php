<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LINE 連携 — 京呉服平田</title>
    @include('line.partials.liff-styles')
</head>
<body data-theme="gold">
<div class="wrap">
    <div class="appbar">
        <span class="seal">京呉服平田</span>
        <h1>LINE連携</h1>
        <div class="rule"></div>
        <div class="sub">ご予約・お客様情報との連携</div>
    </div>

    @if ($error)
        <div class="card"><div class="msg err">{{ $error }}</div></div>
    @else
        <div class="card">
            <p class="lead">公式アカウントの友だち追加ありがとうございます。<br>下のフォームから、ご予約・お客様情報と連携してください。</p>

            <label class="fld" for="lookup_key">電話番号</label>
            <input class="input" id="lookup_key" type="tel" inputmode="tel" maxlength="20" placeholder="例) 090-1234-5678" autocomplete="tel">
            <p class="help">ご予約／ご来店時にご登録のお電話番号（ハイフン有無どちらでも可）。</p>

            <label class="fld" for="kana">お名前カナ<span class="opt">任意</span></label>
            <input class="input" id="kana" type="text" maxlength="50" placeholder="例) ヒラタ ハナコ" autocomplete="off">
            <p class="help">複数のご登録がある場合の照合に使います。半角・全角・スペースは問いません。</p>

            <button type="button" class="btn btn-line" id="btn" style="margin-top:18px">連携する</button>
            <p id="status" class="hint" aria-live="polite"></p>
        </div>
        <p class="muted center">うまく連携できない場合は、このトークに「お名前」と「お電話番号」をお送りください。スタッフが対応いたします。</p>
    @endif
</div>

@unless ($error)
<script src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
<script>
    (function () {
        const liffId = @json($liffId);
        const PENDING_KEY = 'liff_pending_welcome_link';
        const matchUrl = @json(route('line.liff.welcome.match'));
        const resumeUrl = @json(route('line.liff.welcome'));

        const statusEl = document.getElementById('status');
        const btn = document.getElementById('btn');
        const lookupInput = document.getElementById('lookup_key');
        const kanaInput = document.getElementById('kana');

        function setStatus(text, className) {
            statusEl.textContent = text || '';
            statusEl.className = className || 'hint';
        }

        async function postMatch() {
            const idToken = liff.getIDToken();
            if (!idToken) {
                btn.disabled = false;
                setStatus('ID トークンを取得できませんでした。時間をおいて再度お試しください。', 'err');
                return;
            }
            const lookupKey = (lookupInput.value || '').trim();
            if (!lookupKey) {
                btn.disabled = false;
                setStatus('電話番号を入力してください。', 'err');
                return;
            }
            const kana = (kanaInput.value || '').trim();

            const res = await fetch(matchUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ id_token: idToken, lookup_key: lookupKey, kana: kana }),
            });
            const raw = await res.text();
            var data = {};
            try { data = raw ? JSON.parse(raw) : {}; } catch (e) { data = {}; }
            if (!res.ok) {
                btn.disabled = false;
                setStatus(data.message || ('連携に失敗しました（HTTP ' + res.status + '）'), 'err');
                return;
            }
            setStatus(data.message || '連携が完了しました。', 'ok');
        }

        async function run() {
            if (typeof liff === 'undefined') {
                setStatus('LINE の SDK を読み込めませんでした。通信状況をご確認のうえ再読み込みしてください。', 'err');
                return;
            }
            btn.disabled = true;
            setStatus('処理中…', 'hint');
            try {
                await liff.init({ liffId });
                if (!liff.isLoggedIn()) {
                    btn.disabled = false;
                    sessionStorage.setItem(PENDING_KEY, '1');
                    setStatus('LINE にログインします。完了後、再度ボタンを押してください。', 'hint');
                    liff.login({ redirectUri: resumeUrl });
                    return;
                }
                await postMatch();
            } catch (e) {
                btn.disabled = false;
                setStatus('エラー: ' + ((e && e.message) ? e.message : String(e)), 'err');
            }
        }

        async function bootAfterLogin() {
            if (typeof liff === 'undefined') return;
            try { await liff.init({ liffId }); } catch (e) { return; }
            if (sessionStorage.getItem(PENDING_KEY) === '1' && liff.isLoggedIn()) {
                sessionStorage.removeItem(PENDING_KEY);
                setStatus('LINE ログインを確認しました。フォームを入力して「連携する」を押してください。', 'hint');
            }
        }

        btn.addEventListener('click', run);
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', bootAfterLogin);
        } else {
            bootAfterLogin();
        }
    })();
</script>
@endunless
</body>
</html>
