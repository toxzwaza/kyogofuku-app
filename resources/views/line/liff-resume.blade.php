<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>LINE 連携</title>
    <style>
        body { font-family: system-ui, sans-serif; padding: 1.25rem; max-width: 28rem; margin: 0 auto; font-size: 0.875rem; color: #374151; }
        .err { color: #b91c1c; }
    </style>
</head>
<body>
    <p id="msg">LINE ログインを完了しています…</p>
    <script src="https://static.line-scdn.net/liff/edge/2/sdk.js"></script>
    <script>
        (function () {
            var liffId = @json($liffId);
            var msgEl = document.getElementById('msg');
            var p = location.pathname;
            var i = p.indexOf('/line/liff/resume');
            var prefix = i > 0 ? p.slice(0, i) : '';
            var linkBase = location.origin + prefix + '/line/liff/link';

            function fail(text) {
                msgEl.textContent = text;
                msgEl.className = 'err';
            }

            (async function () {
                var token = sessionStorage.getItem('liff_link_token');
                if (!token) {
                    fail('セッションが見つかりません。最初の連携リンクから開き直してください。');
                    return;
                }
                if (!liffId) {
                    fail('サーバー設定（LIFF ID）がありません。');
                    return;
                }
                if (typeof liff === 'undefined') {
                    fail('LINE SDK を読み込めませんでした。');
                    return;
                }
                try {
                    // この URL に付いた ?code= &state= を LIFF が処理し、ログイン状態になる
                    await liff.init({ liffId: liffId });
                    sessionStorage.removeItem('liff_link_token');
                    location.replace(linkBase + '/' + encodeURIComponent(token));
                } catch (e) {
                    fail('初期化に失敗しました: ' + (e && e.message ? e.message : String(e)));
                }
            })();
        })();
    </script>
</body>
</html>
