# Google Calendar トークン維持の定期実行（Python + HTTP 方式）

Docker でコンテナを分けている構成で、Laravel スケジューラ + supervisor を使わずに、**Python スクリプトで HTTP アクセス**する方式で Google Calendar の refresh トークンを定期実行します。

## 概要

- Laravel アプリにトークン維持用の **HTTP エンドポイント** を用意
- **Python スクリプト** がそのエンドポイントに週1回アクセス
- Python は **VPS ホスト側** で実行（Docker 外）。cron の設定がシンプルになる

```
[VPS cron] → Python スクリプト → HTTP GET → Laravel アプリ (Docker) → Google Calendar API
```

## 1. Laravel 側の設定

### .env に追加

```
GOOGLE_CALENDAR_KEEP_TOKEN_SECRET=ランダムな文字列を設定
```

例: `openssl rand -hex 32` で生成した値などを設定してください。

### エンドポイント

- **URL**: `https://あなたのドメイン/api/google-calendar/keep-token`
- **認証**: `X-Api-Key` ヘッダー または `?token=xxx` クエリパラメータで上記シークレットを送信

## 2. Python スクリプトの実行

### 基本

```bash
python3 scripts/keep_google_calendar_token_alive.py \
  --url https://あなたのドメイン \
  --token "GOOGLE_CALENDAR_KEEP_TOKEN_SECRETの値"
```

### 環境変数でトークンを渡す場合

```bash
export GOOGLE_CALENDAR_KEEP_TOKEN_SECRET="あなたのシークレット"
python3 scripts/keep_google_calendar_token_alive.py --url https://あなたのドメイン
```

## 3. ConoHa VPS (Ubuntu) で cron を設定

### 3-1. Python がインストールされているか確認

```bash
python3 --version
```

標準の Ubuntu には通常 Python3 が含まれています。

### 3-2. プロジェクトをホストに配置

Laravel プロジェクトを VPS のホスト側にクローンまたは配置し、`scripts/keep_google_calendar_token_alive.py` が存在することを確認します。

Docker でアプリを動かしている場合、プロジェクトはホストの `/var/www/kyogofuku-app` などにマウントされていることが多いです。そのパスを前提にします。

### 3-3. cron を編集

```bash
crontab -e
```

### 3-4. 以下の行を追加（毎週月曜 2:00 に実行）

```cron
0 2 * * 1 cd /path/to/kyogofuku-app && GOOGLE_CALENDAR_KEEP_TOKEN_SECRET="あなたのシークレット" python3 scripts/keep_google_calendar_token_alive.py --url https://あなたのドメイン >> /path/to/kyogofuku-app/storage/logs/keep-token.log 2>&1
```

`/path/to/kyogofuku-app` と `https://あなたのドメイン`、`あなたのシークレット` を実際の値に置き換えてください。

### 3-5. ログを見る

```bash
tail -f /path/to/kyogofuku-app/storage/logs/keep-token.log
```

## 4. 動作確認

手動で実行してレスポンスを確認します。

```bash
cd /path/to/kyogofuku-app
export GOOGLE_CALENDAR_KEEP_TOKEN_SECRET="あなたのシークレット"
python3 scripts/keep_google_calendar_token_alive.py --url https://あなたのドメイン
```

成功時は `{"success":true,"message":"トークン維持処理が成功しました"}` が返ります。

## 5. 注意点

- **GOOGLE_CALENDAR_KEEP_TOKEN_SECRET** は推測困難な長い文字列にしてください。
- エンドポイントは認証なしで呼べると誰でもトークン維持処理を実行できてしまうため、必ずシークレットを設定してください。
- Docker 内の Laravel に到達できる URL（ホストから見て `http://localhost:ポート` や `https://ドメイン`）を `--url` に指定してください。
