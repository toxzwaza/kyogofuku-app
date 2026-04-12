# kyogofuku-app 概要分析（Cursor / 追加開発用）

**対象**: 京呉服平田 基幹システム（Laravel）  
**分析日**: 2026-04-12  
**目的**: 追加開発時の全体像把握（ルート・ドメイン・外部連携の入口を特定する）

---

## 1. 技術スタック

| 層 | 技術 |
|----|------|
| バックエンド | PHP ^8.0.2、**Laravel 9.x**（`laravel/framework` ^9.19） |
| フロント（管理・会員系） | **Inertia.js**（`inertiajs/inertia-laravel` ^0.6）+ **Vue 3**、**Vite 4** |
| ルーティング補助 | **Ziggy**（`tightenco/ziggy`：Laravel ルート名をフロントへ共有） |
| 認証 | **Laravel Breeze**（開発依存）、**Sanctum**（API 用）、メール検証（`verified` ミドルウェア） |
| DB / ORM | **MySQL**（`.env.example` 想定）、**Doctrine DBAL**（スキーマ変更用途）、**staudenmeir/eloquent-eager-limit** |
| ストレージ | **AWS S3**（`league/flysystem-aws-s3-v3`）、画像は **Intervention Image** |
| 外部 API | **Google API Client**（カレンダー等）、**Guzzle**、**Laravel Socialite** |
| メール | **Amazon SES** 受信（`/inbound-mail`）、MIME 解析（`zbateson/mail-mime-parser`） |
| フロントライブラリ | FullCalendar、Chart.js、Fabric.js、Swiper、QRCode、marked、Panzoom 等（`package.json`） |
| PWA | `vite-plugin-pwa`（オフライン対応の布石） |

---

## 2. アプリケーションの性格（ビジネスドメイン）

呉服・成人式関連事業を想定した **顧客・イベント・予約・前撮り・店舗** を中心とした業務システム。

主な業務領域のマッピング:

- **イベント LP / 予約**: 公開 URL `/event/{slug}`、予約フォーム・袴専用フォーム・資料フォーム、サンクスページ
- **管理画面**: `/admin/*`（認証済みユーザー向け）
- **顧客管理（CRM 的）**: 契約、タグ、メモ、写真、制約書、成人式準備項目、担当店舗 等
- **前撮り**: スロット・スタジオ・スケジュール生成
- **スタッフスケジュール**: FullCalendar 連想の API（経費カテゴリ予測など含む）
- **勤怠**: 打刻、仮申請・承認、会社カレンダー、給与計算用設定・シミュレータ
- **LINE**: Messaging API Webhook、LIFF での連携完了、予約・顧客ごとの 1:1 メッセージ
- **マーケ計測**: UTM、GTM（特定ルートのみ）、外部 GAS 向け `/api/utm-analytics`
- **監査・セキュリティ**: `LogActivity` ミドルウェア、アクティビティログ、IP ブロック解除

---

## 3. ディレクトリ構成（開発で触る箇所）

| パス | 内容 |
|------|------|
| `routes/web.php` | メインの HTTP ルート（公開イベント・admin・LINE・SES） |
| `routes/api.php` | Sanctum の `/user`、Google カレンダートークン維持、UTM API |
| `routes/auth.php` | Breeze 由来のログイン・登録・パスワードリセット |
| `app/Http/Controllers/` | コントローラ（`Admin\` 名前空間が管理画面の大半） |
| `app/Models/` | Eloquent モデル（約 49 ファイル） |
| `app/Services/` | ドメインロジックの集約（LINE、Google カレンダー、LP テーマ、勤怠給与 等） |
| `app/Jobs/` | `SyncToGoogleCalendarJob`（非同期同期） |
| `database/migrations/` | マイグレーション（**135** 前後、2026 年も継続更新） |
| `resources/js/Pages/` | Inertia + Vue ページ（公開 `Event/`、管理 `Admin/` 等、**約 96** コンポーネント群） |
| `resources/views/` | Inertia ルートテンプレート `app.blade.php` 等 |
| `config/` | 各種設定（LINE・Google・filesystem 等） |
| `docs/` | 補助ドキュメント（内容は個別確認） |
| `scripts/` | 運用スクリプト |
| `deploy.sh` | デプロイ用シェル |

---

## 4. ルーティング概要

### 4.1 認証後（一般スタッフ）

- `/dashboard` … ダッシュボード
- `/profile` … プロフィール
- `/auth/google/*` … Google OAuth（カレンダー連携）
- `/attendance/*` … 勤怠（打刻・履歴・仮申請・承認 API 等）

### 4.2 公開（未ログイン）

- `/event/{slug}` … イベント LP 表示
- `/event/{slug}/reserve` … 予約ページ
- `POST /event/{event}/reserve` … 予約送信
- `/api/postal-code/search` … 郵便番号検索
- `/document/{document}` … 資料表示
- LINE LIFF: `/line/liff/link/{token}` 等
- Webhook: `POST /webhook/line/messaging`、`POST /webhook/line`
- `POST /inbound-mail` … SES 受信

### 4.3 管理画面 `prefix('admin')` + `auth` + `verified`

イベント・画像・CTA/LP 設定、予約、タイムスロット、店舗、顧客（契約・制約・タグ・LINE）、ユーザー、会場、スケジュール、勤怠（管理者）、制約テンプレ、顧客タグ、アクティビティログ、スライドショー、前撮り、UTM 分析順序 等。  
詳細は **`routes/web.php`** が単一の正本。

### 4.4 API（`routes/api.php`）

- `GET /api/user` … Sanctum 認証ユーザー
- `GET /api/google-calendar/keep-token` … リフレッシュトークン維持（cron / Python から。`X-Api-Key` または token）
- `GET /api/utm-analytics` … UTM 分析（GAS 等。同様に API キー認証）

---

## 5. 主要モデル（ファイル名ベース）

イベント・予約: `Event`, `EventReservation`, `EventTimeslot`, `EventImage`, `EventUtmTracking`, `UtmSource`  
顧客: `Customer`, `CustomerNote`, `CustomerTag`, `CustomerTagAssignment`, `CustomerPhoto`, `CustomerConstraint`, `Contract`, `Plan` 等  
LINE: `CustomerLineContact`, `CustomerLineMessage`, `CustomerLineLinkToken`, `LineUnknownInboundMessage`  
店舗・会場: `Shop`, `Venue`, `CeremonyArea`  
前撮り: `PhotoSlot`, `PhotoStudio`, `PhotoType`  
スケジュール: `StaffSchedule`, `ScheduleParticipant`, `TaskExpenseMapping`  
メール: `Email`, `EmailThread`, `EmailAttachment`  
勤怠: `AttendanceRecord`, `AttendanceBreak`, `AttendancePayrollSetting`, `CompanyCalendarDay`, `WorkAttribute`, `WorkAttributePatternTime`  
その他: `User`, `ActivityLog`, `BlockedIp`, `Document`, `DocumentImage`, `Slideshow`, `SlideshowImage`, `GoogleCalendarEventSync`, `S3TestItem`

---

## 6. サービス層（ビジネスロジックの置き場）

`app/Services/` に集約されている処理の例:

- `GoogleCalendarSyncService` … 予約と Google カレンダーの同期
- `Line\LineMessagingService`, `LineIdTokenVerifier`, `EventLineShopResolver`, `ReservationLineContactMigrator`
- `Event\EventPublicPageService`, `EventReservationCalendarPresentationService`, `EventReservationScheduleBootstrapService`
- `LpDesign\LpThemeResolver` … LP デザイン解決
- `AttendancePayrollTimeService`, `AttendanceScopeService` … 勤怠・給与計算まわり

追加開発では **コントローラを薄く**し、ここへの抽出や既存サービスの拡張が安全。

---

## 7. フロントエンド（Inertia）

- エントリ: `resources/js/app.js`（慣例）
- ページ: `resources/js/Pages/` … `Admin/*`（管理）、`Event/*`（公開 LP・フォーム）、`Attendance/*`、`Auth/*`、`Dashboard.vue` 等
- グローバル共有: `HandleInertiaRequests` で `auth.user`（勤怠権限フラグ付き）、`ziggy`、`flash`、`gtmId`（ルート依存）を付与

---

## 8. 環境変数・外部連携（`.env.example` より）

設定・秘密情報は **リポジトリに含めず** `.env` で管理。

| 区分 | 変数例 | 用途 |
|------|--------|------|
| Google | `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_CALENDAR_REFRESH_TOKEN`, `GOOGLE_CALENDAR_RESERVATION_OWNER_USER_ID` | OAuth・カレンダー同期 |
| AWS | `AWS_*`, `FILESYSTEM_DISK` | S3 アップロード・署名 URL |
| LINE | `LINE_MESSAGING_*`, `LINE_LOGIN_CHANNEL_ID`, `LINE_LIFF_ID` 等 | Webhook・LIFF・友だち追加 URL |
| フロント | `VITE_GOOGLE_MAPS_EMBED_API_KEY` | 会場マップ埋め込み |
| API | `UTM_ANALYTICS_API_SECRET` | UTM 分析 API 認証 |

メールは SMTP（開発は Mailpit 想定）および SES 受信ルートの組み合わせ。

---

## 9. ミドルウェア・運用上の注意

- **Web グループ**: `HandleInertiaRequests`, `VerifyCsrfToken`, **`LogActivity`**（全リクエストの活動ログ）
- **API グループ**: `throttle:api`
- デバッグ・検証用ルート: `/debug-gd`, `/test`, `/s3-test`, `/test-ses-mail`, `/test-line` 等が **本番では無効化またはアクセス制限**を検討すべき

---

## 10. テスト・品質

- **PHPUnit 9**（`phpunit.xml`）
- 静的解析: **Laravel Pint**
- Cursor ルール: `.cursor/rules/testing-commands.mdc`（テストコマンド方針があれば参照）

---

## 11. 追加開発時の推奨着手順

1. **要件に該当するルート**を `routes/web.php` / `api.php` で特定する。  
2. 対応する **Controller** と **Vue Page**（`resources/js/Pages/`）を開く。  
3. 永続化は **Model + migration**、複雑ロジックは **`app/Services`** を確認・拡張。  
4. LINE / Google / SES / S3 は **config と .env**、および既存の `Services` 実装を踏襲する。  
5. 本番影響のある Webhook・公開 API は **認証・署名検証・レート制限**を改めて確認する。

---

## 12. 補足

- `README.md` は Laravel 既定の英語テンプレートのまま。業務仕様の「正」はコード・マイグレーション・社内ドキュメント（Notion 等）と併用すること。
- プロジェクト直下に業務向けメモ（文字化けしているファイル名の `.md` / `.txt`）が存在する場合がある。OS 表示エンコーディングに注意。

---

*本ドキュメントはリポジトリ構造とソースから機械的に整理したものであり、運用ルール・契約上の制約は別紙の仕様書と照合してください。*
