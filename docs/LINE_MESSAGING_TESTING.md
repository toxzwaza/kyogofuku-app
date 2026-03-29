# LINE 1:1（Messaging API / LIFF）のテスト手順

設定が一通り終わったあと、**Webhook 受信 → 不明キューまたは顧客スレッド**、**LIFF 連携**、**管理画面からの送信**までを確認するための手順です。  
前提のチャネル構成は [LINE_MESSAGING_SETUP.md](./LINE_MESSAGING_SETUP.md) と同じ（Messaging API 1 本 + LINEログインで LIFF）を想定しています。

---

## 1. テスト前のチェックリスト

| 項目 | 内容 |
|------|------|
| `.env` | `LINE_MESSAGING_CHANNEL_SECRET` / `LINE_MESSAGING_CHANNEL_ACCESS_TOKEN` / `LINE_LOGIN_CHANNEL_ID` / `LINE_LIFF_ID`（または `LINE_MESSAGING_LIFF_ID`）が埋まっている |
| 設定反映 | 変更後は `php artisan config:clear`（本番で `config:cache` している場合は再キャッシュ） |
| Webhook URL | LINE Developers（**Messaging API** チャネル）で `https://{ドメイン}/webhook/line/messaging` が登録され、**利用オン** |
| LIFF | **LINEログイン**チャネル側で Endpoint が `https://{ドメイン}/line/liff/link` と一致 |
| 友だち追加 | テスト用の LINE アカウントが **1:1 用の公式アカウント（Messaging API のボット）を友だち追加**済み |
| 管理画面 | テスト用の **顧客**に **担当店舗（`shop_id`）** が付いている |
| 予約者 LINE | イベントに **店舗が紐づいている**こと（`event_shop`）。受付メールに LIFF URL が付く |

**ローカル PC だけで Webhook を試す場合:** LINE は **HTTPS の公開 URL** にしか Webhook を送れません。  
[ngrok](https://ngrok.com/) などで `https://xxxx.ngrok-free.app` のような URL を発行し、そのベース URL を **`APP_URL` と Webhook / LIFF の Endpoint / Callback URL** に揃えてください。

**ngrok 利用時の注意:** 以前は `APP_URL=http://127.0.0.1:8000` のまま ngrok 経由で LIFF ページだけ開くと、画面内の `fetch` が localhost を指して **400 Bad Request** になることがありました。LIFF の HTML は **今開いているホスト**へ POST するよう修正済みですが、**発行する連携リンクの QR／URL** は引き続き `APP_URL` から生成されるため、検証中は **`.env` の `APP_URL` も ngrok の HTTPS URL** に合わせると安全です。LINE コンソールの **Callback URL** も `https://（ngrok のホスト）/line/liff/resume` を登録してください。

---

## 2. Webhook（受信）のテスト

### 2.1 LINE コンソールの「検証」

1. LINE Developers → **Messaging API** チャネル → **Messaging API** タブ。
2. **Webhook URL** が `…/webhook/line/messaging` になっていることを確認。
3. **Webhook の利用** がオンであることを確認。
4. **検証（Verify）** を実行する。

**成功:** アプリが 200 を返し、コンソール上で成功と表示される。  
**失敗:** URL 誤り、HTTPS でない、`LINE_MESSAGING_CHANNEL_SECRET` とチャネルの不一致、ファイアウォール、ルート未登録（`php artisan route:list` で `webhook.line.messaging` を確認）などを疑う。

### 2.2 実メッセージで受信する

1. 友だち追加済みのスマホから、対象の公式アカウントに **テキストメッセージ**を送る。
2. 次のどちらかを確認する。

**まだ LIFF 連携していない LINE ユーザー**

- 管理画面 → **LINE 不明メッセージ** にスレッドが増える（店舗は「店舗未分類」になり得る）。
- 一覧に出なければ `storage/logs/laravel.log` にエラーが出ていないか確認。

**すでに `customer_line_contacts` に紐づいているユーザー**

- 該当 **顧客詳細** の LINE 欄で、**受信メッセージ**にその文面が表示される（不明キューには入らない）。

---

## 3. LIFF 連携のテスト

1. 管理画面にログインし、**担当店舗付きの顧客**を開く。
2. LINE 連携で **連携用リンクを発行**する（ボタン名は画面表記に従う）。
3. 発行された `https://liff.line.me/...` または **`https://{APP_URL}/line/liff/link/{UUID}`** を **スマホの LINE** で開く（同一テストアカウント推奨）。
4. 画面では **友だち追加**（任意だが推奨）→ **連携する** の順。案内に従い連携を完了する。

**成功の目安**

- エラーなく完了メッセージが表示される。
- 顧客詳細に **LINE 連絡先** が表示され、以降はそのユーザーからの送信が **不明ではなく会話**に入る。

**失敗しやすい点**

- `LINE_LOGIN_CHANNEL_ID` が **LINEログイン**チャネルの Channel ID になっていない（Messaging の ID を入れている等）。
- LIFF の **Endpoint URL** が `https://{APP_URL}/line/liff/link` と一致していない（末尾スラッシュの有無も含めて確認）。発行 URL は **`/line/liff/link/{UUID}`** まで続く。
- **`APP_URL`** とブラウザで開いているドメインが一致していない（リンクが別ホストになる）。
- 顧客に **担当店舗が未設定**でリンク発行や完了時に弾かれる。

---

## 4. 管理画面からの送信（Push）のテスト

1. 上記で **連携済み**の顧客を開き、LINE の会話 UI から **短いテキスト**を送信する。
2. スマホの LINE で **公式アカウントから届く**ことを確認する。

**失敗しやすい点**

- `LINE_MESSAGING_CHANNEL_ACCESS_TOKEN` が **Messaging API** チャネルの長期トークンと一致していない。
- ユーザーが **友だち追加していない**（Push が届かない・エラーになることがある）。

---

## 5. ログ・デバッグの見方

| 確認先 | 用途 |
|--------|------|
| `storage/logs/laravel.log` | Webhook 署名エラー、Push 失敗、ID トークン検証失敗など |
| ブラウザ開発者ツール（Network） | LIFF 完了時の `POST /line/liff/complete` のステータス・レスポンス |
| `php artisan route:list --name=line` | `webhook.line.messaging` / `line.liff.link` / `line.liff.complete` の有無 |

Webhook が **400 invalid signature** のときは、`.env` の **`LINE_MESSAGING_CHANNEL_SECRET`** と **今開いている Messaging API チャネル**の Channel secret が同一か再確認してください。

---

## 6. 最低限のテスト順（おすすめ）

1. LINE コンソールで **Webhook 検証**が通る。  
2. 未連携の LINE で **1 通送る** → **LINE 不明メッセージ**に載る。  
3. **LIFF で連携**する。  
4. もう **1 通送る** → **顧客の会話**に載る。  
5. **管理画面から返信**する → スマホに届く。

この順で問題なければ、本番運用に載せる前の一通りの動作は確認できています。

---

## 関連ドキュメント

- [LINE_MESSAGING_SETUP.md](./LINE_MESSAGING_SETUP.md) — チャネル作成・`.env`・Webhook / LIFF の設定
