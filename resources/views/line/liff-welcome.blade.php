<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LINE 連携 — 京呉服平田</title>
    <style>
        body { font-family: system-ui, sans-serif; padding: 1.25rem; max-width: 28rem; margin: 0 auto; color: #222; }
        h1 { font-size: 1.125rem; margin: 0 0 0.75rem; }
        .lead { font-size: 0.875rem; color: #444; margin: 0 0 1rem; line-height: 1.6; }
        .err { color: #b91c1c; margin-bottom: 1rem; font-size: 0.875rem; }
        .ok { color: #15803d; }
        .hint { font-size: 0.8125rem; color: #6b7280; margin-top: 0.5rem; }
        label { display: block; margin: 0.75rem 0 0.25rem; font-size: 0.875rem; font-weight: 600; }
        input, button { width: 100%; box-sizing: border-box; padding: 0.625rem 0.75rem; font-size: 1rem; border-radius: 0.375rem; }
        input { border: 1px solid #d1d5db; }
        button { margin-top: 1rem; background: #06c755; color: #fff; border: none; cursor: pointer; touch-action: manipulation; font-weight: 600; }
        button:disabled { opacity: 0.6; cursor: not-allowed; }
        #status { margin-top: 1rem; font-size: 0.875rem; white-space: pre-wrap; word-break: break-word; }
        .step-box { border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1rem; background: #fafafa; }
        .step-title { font-size: 0.75rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; }
        .help { font-size: 0.75rem; color: #6b7280; margin-top: 0.25rem; line-height: 1.5; }
    </style>
</head>
<body>
    <h1>ご予約・お客様情報と連携</h1>

    @if ($error)
        <p class="err">{{ $error }}</p>
    @else
        <p class="lead">公式アカウントを友だち追加していただきありがとうございます。<br>下のフォームからご予約・お客様情報と連携してください。</p>

        <div class="step-box">
            <div class="step-title">入力</div>
            <label for="lookup_key">電話番号</label>
            <input id="lookup_key" type="tel" inputmode="tel" maxlength="20" placeholder="例) 090-1234-5678" autocomplete="tel">
            <p class="help">ご予約時／ご来店時にご登録いただいたお電話番号を入力してください（ハイフン有無どちらでも可）。</p>

            <label for="kana">お名前カナ（任意）</label>
            <input id="kana" type="text" maxlength="50" placeholder="例) ヒラタ ハナコ" autocomplete="off">
            <p class="help">複数のご登録がある場合の照合に使います。半角・全角・スペース有無は問いません。</p>

            <button type="button" id="btn">連携する</button>
        </div>

        <p id="status" class="hint" aria-live="polite"></p>
    @endif

    @unless ($error)
    <script src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
    <script>
        (function () {
            const liffId = @json($liffId);
            const PENDING_KEY = 'liff_pending_welcome_link';

            function liffPathPrefix() {
                var p = location.pathname;
                var i = p.indexOf('/line/liff/welcome');
                if (i > 0) {
                    return p.slice(0, i);
                }
                return '';
            }
            function liffApiBase() {
                return location.origin + liffPathPrefix();
            }
            const matchUrl = liffApiBase() + '/line/liff/welcome/match';
            const resumeUrl = location.origin + liffPathPrefix() + '/line/liff/welcome';

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
                    setStatus('ID トークンを取得できませんでした。LIFF の Scope に openid を含めてください。', 'err');
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
                    body: JSON.stringify({
                        id_token: idToken,
                        lookup_key: lookupKey,
                        kana: kana,
                    }),
                });
                const raw = await res.text();
                var data = {};
                try {
                    data = raw ? JSON.parse(raw) : {};
                } catch (parseErr) {
                    data = {};
                }
                if (!res.ok) {
                    btn.disabled = false;
                    setStatus(data.message || ('連携に失敗しました（HTTP ' + res.status + '）'), 'err');
                    return;
                }
                setStatus(data.message || '完了しました。', 'ok');
            }

            async function run() {
                if (typeof liff === 'undefined') {
                    setStatus('LINE の SDK を読み込めませんでした。通信状況を確認し、ページを再読み込みしてください。', 'err');
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
                    const msg = (e && e.message) ? e.message : String(e);
                    setStatus('エラー: ' + msg, 'err');
                }
            }

            async function bootAfterLogin() {
                if (typeof liff === 'undefined') return;
                try {
                    await liff.init({ liffId });
                } catch (e) {
                    return;
                }
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
