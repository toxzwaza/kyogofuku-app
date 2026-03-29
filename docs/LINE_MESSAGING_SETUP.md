# LINE（Messaging API / LIFF）連携 — LINE 側の設定手順と注意点

本アプリの「顧客との 1:1 メッセージ」「LIFF による友だち紐づけ」を動かすために、**LINE Developers** および **公式アカウント** 側で行う設定をまとめます。

**1:1 の Messaging API（友だち・Webhook・Push）は全店舗で 1 チャネル**とし、シークレット・長期トークンは **`.env` の `LINE_MESSAGING_CHANNEL_*`** に設定します。

**LIFF は別途「LINEログイン」チャネルに作成します。** LINE の仕様上、**Messaging API チャネルには LIFF アプリを追加できません**（コンソールに「LINEログインチャネルを使用してください」と表示されます）。ID トークン検証には、**LINEログインチャネルの Channel ID** を **`.env` の `LINE_LOGIN_CHANNEL_ID`** に設定します。

業務上の **担当店舗** は引き続き **`customers.shop_id`** および **`customer_line_contacts.shop_id`** に保持します。

**設定後の動作確認**は [LINE_MESSAGING_TESTING.md](./LINE_MESSAGING_TESTING.md) を参照してください。

**イベント予約者向け LINE:** まだ顧客登録されていない予約について、**イベントに店舗が 1 件以上紐づいている**とき、受付完了メールに **LIFF 連携 URL** が含まれます（`customer_line_link_tokens.event_reservation_id`）。連携後のメッセージは **管理画面 → 予約詳細** の LINE ブロックで表示され、**顧客登録または予約への顧客紐づけ**で同一 `customer_line_contact` が顧客に移り、**過去のやり取りも顧客詳細に引き継がれます**。予約は **本人 1 LINE のみ**（表示名「本人」固定）です。

**LIFF 画面（`/line/liff/link/{token}`）** では、**ステップ 1 で友だち追加 URL を開くボタン**と、**ステップ 2 で連携する**の順を 1 ページにまとめています。友だち追加 URL は **`config('line.line_official_add_friend_url')`**（`.env` の `LINE_OFFICIAL_ADD_FRIEND_URL`、未設定時は `https://lin.ee/R7RUNlX`）です。

---

## 前提の整理

| 用途 | 本アプリ側 | LINE 側のチャネル種別 |
|------|------------|------------------------|
| 予約通知など **グループへの Push** | `shops.line_group_id` 等 | 別系統（`.env` の `LINE_CHANNEL_TOKEN` 等） |
| **顧客 1:1（Webhook・Push）** | `LINE_MESSAGING_CHANNEL_SECRET` / `ACCESS_TOKEN` | **Messaging API** チャネル 1 本 |
| **LIFF・連携時の ID トークン検証** | `LINE_LIFF_ID`（または `LINE_MESSAGING_LIFF_ID`）+ **`LINE_LOGIN_CHANNEL_ID`** | **LINEログイン** チャネル（同一プロバイダー内で作成） |

**グループ通知用チャネル・Messaging API チャネル・LINEログインチャネルは別物です。** 混同しないでください。

---

## 1. LINE Developers でプロバイダーとチャネルを用意する

### 1.1 共通

1. [LINE Developers Console](https://developers.line.biz/) にログインする。
2. **Provider** を作成（または既存を使用）する。

### 1.2 Messaging API（1:1 ボット）

1. 同じプロバイダーで **Messaging API** のチャネルを **1 本**作成する。
2. チャネルを **公開** し、公式アカウントとして利用する。
3. **Messaging API** タブで長期チャネルアクセストークンを発行する。
4. **Basic settings** の **Channel secret** を控える（Webhook 署名検証用）。

### 1.3 LINEログイン（LIFF 用）

1. 同じプロバイダーで **LINEログイン** のチャネルを作成する（チャネルタイプで「LINEログイン」を選ぶ）。
2. **LIFF** はこのチャネルのコンソールから追加する（Messaging API チャネルの LIFF タブでは新規追加できません）。
3. **Basic settings** の **Channel ID** を控える → これを **`LINE_LOGIN_CHANNEL_ID`** に設定する（ID トークン検証の `client_id`）。

Messaging の公式アカウントと友だち関係を取る必要がある運用では、LINE の案内に従い **ログインチャネルと Messaging チャネルの連携**（同じボットとしての紐づけ等）を確認してください。

---

## 2. 本アプリの `.env` 設定

`config/line.php` が参照します。

| 環境変数 | 内容 |
|----------|------|
| `LINE_MESSAGING_CHANNEL_SECRET` | **Messaging API** チャネル Basic settings の **Channel secret**（`/webhook/line/messaging` の署名検証） |
| `LINE_MESSAGING_CHANNEL_ACCESS_TOKEN` | **Messaging API** タブの **チャネルアクセストークン（長期）**（Push 送信） |
| `LINE_LOGIN_CHANNEL_ID` | **LINEログイン** チャネル Basic settings の **Channel ID**（LIFF の **ID トークン検証**用。必須） |
| `LINE_LIFF_ID` | LIFF アプリの **LIFF ID**（推奨。未設定のとき `LINE_MESSAGING_LIFF_ID` を参照） |

**後方互換:** `LINE_LOGIN_CHANNEL_ID` が空のときだけ、ID トークン検証に `LINE_MESSAGING_CHANNEL_ID` を使います（古い構成向け。新規は `LINE_LOGIN_CHANNEL_ID` を設定してください）。

設定変更後は `php artisan config:clear` を実行するか、キャッシュを再生成してください。

---

## 3. Messaging API の Webhook

**Messaging API** チャネルの **「Messaging API」タブ** で次を行います。

- **Webhook URL** に次を設定する（店舗 ID は含めません）。

  ```
  https://{あなたのドメイン}/webhook/line/messaging
  ```

- **Webhook の利用** を **オン** にする。
- **検証（Verify）** を実行する。

**既存の `POST /webhook/line`**（[`LineWebhookController`](app/Http/Controllers/LineWebhookController.php)）は **グループ通知用**です。チャネルを分けていれば URL の衝突は起きません。

---

## 4. LIFF の作成（LINEログインチャネル上）

**LINEログイン** チャネルを選んだ状態で、コンソールの **LIFF** からアプリを追加します。

| 項目 | 推奨値・注意 |
|------|----------------|
| **LIFF ID** | 発行後の ID を `LINE_LIFF_ID`（または `LINE_MESSAGING_LIFF_ID`）に設定する。 |
| **Endpoint URL** | `https://{ドメイン}/line/liff/link`（末尾スラッシュなし推奨。`APP_URL` と一致） |
| **Scope** | **openid**・**profile**（ID トークン取得のため `openid` が必要） |
| **Callback URL**（LINEログイン） | `https://{ドメイン}/line/liff/resume` を **必ず 1 件追加**する（`liff.login()` の戻り先。未登録だとログイン後に連携が進みません） |

管理画面が発行する連携 URL は **`https://{APP_URL}/line/liff/link/{トークンUUID}`**（**自ドメイン・パスにトークン**）です。LINE のアプリ内ブラウザや `liff.line.me?link_token=` 経由では、環境によって **クエリが落ちる**ことがあるため、この形式にしています。お客様は **LINE 内で当該 URL を開く**と LIFF SDK が利用できます。

### LINE コンソールに表示される案内について

「**Messaging API チャネルには、LIFF アプリを追加できません。LINEログインチャネルに追加してください**」という表示は、上記のとおり **仕様**です。必ず **LINEログイン** チャネル側で LIFF を作成してください。

また、**LINEミニアプリ**への統合・新規はミニアプリ推奨といった案内が出ることがあります。日本・台湾など対象地域でミニアプリチャネルを作成できる場合は LINE の最新ドキュメントに従って検討できます。**従来の LIFF** も引き続き利用可能で、本アプリは **LIFF + ID トークン**方式を想定しています。

---

## 5. 友だち追加・テストユーザー

- 1:1 でメッセージを送るには、ユーザーが **当該 Messaging API の公式アカウントを友だち追加**している必要があります。
- 開発中は **テスト用アカウント** を追加すると検証しやすいです。

---

## 6. 店舗・顧客データとの関係

- **店舗マスタ**には **グループ用**の `line_group_id` のみ。編集画面に **共通 Webhook URL** の案内があります。
- **顧客**には **担当店舗**として `shop_id` が必要です（未設定だと連携リンク発行・送信でエラーになります）。
- **不明メッセージ**の新規受信は **`shop_id` が null**（「店舗未分類」）で溜まる場合があります。紐づけ時に顧客の `shop_id` が `customer_line_contacts` に保存されます。

---

## よくあるミス・トラブル

### Webhook

1. **URL** — `/webhook/line/messaging` まで含める。
2. **シークレット** — `LINE_MESSAGING_CHANNEL_SECRET` は **Messaging API** チャネルのもの。
3. **HTTPS のみ**、**ルートキャッシュ**、**TrustProxies** などは従来どおり注意。

### LIFF・ログイン

1. **Messaging API チャネルで LIFF を作ろうとしていないか** — **LINEログイン** チャネルで作成する。
2. **ID トークン検証が失敗する** — `.env` の **`LINE_LOGIN_CHANNEL_ID` が LINEログイン** チャネルの Channel ID か確認する（Messaging の Channel ID を入れていないか）。
3. **LIFF ID と Channel ID を取り違えていないか**。
4. **Endpoint URL** が `https://{APP_URL}/line/liff/link` と一致しているか（**発行 URL は `/line/liff/link/{UUID}` まで続く**が、LIFF の Endpoint はベースまででよい）。
5. **トークンなし**で `/line/liff/link` だけ開くとエラー（仕様）。**`APP_URL` が実際に開く URL と一致**しているか（`config:clear`）。

---

## 参考（本アプリのルート）

| メソッド | パス | 用途 |
|----------|------|------|
| POST | `/webhook/line/messaging` | 顧客 1:1 Messaging Webhook（`LINE_MESSAGING_CHANNEL_SECRET` で署名検証） |
| POST | `/webhook/line` | 既存のグループ通知用 Webhook |
| GET | `/line/liff/link/{token}` | LIFF 連携画面（推奨・トークンはパス） |
| GET | `/line/liff/link` | レガシー（`?link_token=`） |
| GET | `/line/liff/resume` | `liff.login()` 後のコールバック（Callback URL に登録） |
| POST | `/line/liff/complete` | LIFF からの連携完了（`LINE_LOGIN_CHANNEL_ID` で ID トークン検証） |

---

## 更新履歴

- LIFF を **LINEログインチャネル**前提に整理。`LINE_LOGIN_CHANNEL_ID` を追加。`LINE_MESSAGING_CHANNEL_ID` は ID トークン検証のフォールバックのみ。
- 単一チャネル化（Messaging 1 本・`/webhook/line/messaging`）および `customers.shop_id` 維持。
