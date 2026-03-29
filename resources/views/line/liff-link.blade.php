<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LINE 連携</title>
    <style>
        body { font-family: system-ui, sans-serif; padding: 1.25rem; max-width: 28rem; margin: 0 auto; }
        .err { color: #b91c1c; margin-bottom: 1rem; }
        .ok { color: #15803d; }
        .hint { font-size: 0.8125rem; color: #6b7280; margin-top: 0.5rem; }
        label { display: block; margin: 0.75rem 0 0.25rem; font-size: 0.875rem; }
        input, button { width: 100%; box-sizing: border-box; padding: 0.5rem 0.75rem; font-size: 1rem; }
        button { margin-top: 1rem; background: #06c755; color: #fff; border: none; border-radius: 0.375rem; cursor: pointer; touch-action: manipulation; }
        button.secondary { background: #2563eb; margin-top: 0.75rem; }
        button:disabled { opacity: 0.6; cursor: not-allowed; }
        #status { margin-top: 1rem; font-size: 0.875rem; white-space: pre-wrap; word-break: break-word; }
        .step-box { border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1rem; background: #fafafa; }
        .step-title { font-size: 0.75rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.5rem; }
    </style>
</head>
<body>
    <h1 style="font-size:1.125rem;">LINE 連携</h1>

    @if ($error)
        <p class="err">{{ $error }}</p>
    @else
        @if (!empty($addFriendUrl))
            <div class="step-box">
                <div class="step-title">ステップ 1</div>
                <p style="font-size:0.875rem;color:#444;margin:0 0 0.5rem;">まず公式アカウントを友だち追加してください（トークでやり取りするために必要です）。</p>
                <button type="button" class="secondary" id="btnAddFriend">公式アカウントを友だち追加</button>
            </div>
        @endif

        <div class="step-box">
            <div class="step-title">{{ !empty($addFriendUrl) ? 'ステップ 2' : '連携' }}</div>
            @if (($linkFlowMode ?? 'customer') === 'reservation')
                <p style="font-size:0.875rem;color:#444;margin:0;">イベント予約のご本人として連携します（表示名は「本人」固定）。</p>
            @else
                <p style="font-size:0.875rem;color:#444;margin:0;">表示名（例：本人・母）を入力して連携してください。</p>
                <label for="label">表示名</label>
                <input id="label" type="text" maxlength="50" placeholder="お客様" value="{{ e($suggestedLabel ?? 'お客様') }}" autocomplete="off">
            @endif
            <button type="button" id="btn">連携する</button>
        </div>
        <p id="status" class="hint" aria-live="polite"></p>
    @endif

    @unless ($error)
    <script src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
    <script>
        (function () {
            const liffId = @json($liffId);
            const linkToken = @json($linkToken);
            const addFriendUrl = @json($addFriendUrl ?? '');
            const linkFlowMode = @json($linkFlowMode ?? 'customer');
            /** url() は APP_URL 基準のため ngrok 等で別ホストになると fetch が誤った先へ飛び 400 になる。常に「今開いているページ」と同一オリジンに送る */
            function liffPathPrefix() {
                var p = location.pathname;
                var i = p.indexOf('/line/liff/link');
                if (i > 0) {
                    return p.slice(0, i);
                }
                return '';
            }
            function liffApiBase() {
                return location.origin + liffPathPrefix();
            }
            var completeUrl = liffApiBase() + '/line/liff/complete';
            var resumeUrl = liffApiBase() + '/line/liff/resume';
            const statusEl = document.getElementById('status');
            const btn = document.getElementById('btn');
            const btnAddFriend = document.getElementById('btnAddFriend');
            const labelInput = document.getElementById('label');
            const PENDING_KEY = 'liff_pending_line_link';

            function setStatus(text, className) {
                statusEl.textContent = text || '';
                statusEl.className = className || 'hint';
            }

            function openAddFriendUrl() {
                if (!addFriendUrl) return;
                try {
                    if (typeof liff !== 'undefined' && typeof liff.openExternalBrowser === 'function') {
                        liff.openExternalBrowser(addFriendUrl);
                        return;
                    }
                } catch (e) {}
                window.open(addFriendUrl, '_blank', 'noopener,noreferrer');
            }

            if (btnAddFriend) {
                btnAddFriend.addEventListener('click', function () {
                    openAddFriendUrl();
                });
            }

            async function postComplete() {
                const idToken = liff.getIDToken();
                if (!idToken) {
                    btn.disabled = false;
                    setStatus('ID トークンを取得できませんでした。LIFF の Scope に openid を含めてください。', 'err');
                    return;
                }
                var labelPayload = '';
                if (linkFlowMode !== 'reservation' && labelInput) {
                    labelPayload = (labelInput.value || '').trim();
                }
                const res = await fetch(completeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        id_token: idToken,
                        link_token: linkToken,
                        label: labelPayload,
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
                    var detail = data.message || '';
                    if (!detail && raw && raw.indexOf('{') !== 0) {
                        detail = 'サーバーが JSON 以外を返しました。.env の APP_URL を「今ブラウザで開いている URL（ngrok 含む）」と揃えるか、プロキシ設定を確認してください。';
                    }
                    setStatus(detail || ('連携に失敗しました（HTTP ' + res.status + '）'), 'err');
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
                        sessionStorage.setItem('liff_link_token', linkToken);
                        sessionStorage.setItem(PENDING_KEY, '1');
                        setStatus('LINE にログインします。完了後、自動で連携処理に進みます。', 'hint');
                        liff.login({ redirectUri: resumeUrl });
                        return;
                    }

                    await postComplete();
                } catch (e) {
                    btn.disabled = false;
                    const msg = (e && e.message) ? e.message : String(e);
                    setStatus('エラー: ' + msg, 'err');
                }
            }

            async function bootAutoAfterLogin() {
                if (typeof liff === 'undefined') {
                    return;
                }
                if (sessionStorage.getItem(PENDING_KEY) !== '1') {
                    return;
                }
                try {
                    await liff.init({ liffId });
                    if (!liff.isLoggedIn()) {
                        return;
                    }
                    sessionStorage.removeItem(PENDING_KEY);
                    btn.disabled = true;
                    setStatus('ログインを確認しました。連携を完了しています…', 'hint');
                    await postComplete();
                } catch (e) {
                    btn.disabled = false;
                    sessionStorage.removeItem(PENDING_KEY);
                    setStatus('エラー: ' + ((e && e.message) ? e.message : String(e)), 'err');
                }
            }

            btn.addEventListener('click', run);
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', bootAutoAfterLogin);
            } else {
                bootAutoAfterLogin();
            }
        })();
    </script>
    @endunless
</body>
</html>
