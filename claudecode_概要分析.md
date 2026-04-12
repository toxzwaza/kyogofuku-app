# 京呉服平田 基幹システム 概要分析

## 1. 概要

京呉服平田のイベント予約管理システム（kyogofuku-app）は、呉服店の成人式などのイベント予約業務全体をデジタル化するLaravelベースのWebアプリケーションです。顧客からの予約受付・資料請求・問い合わせフォームの提供、店舗スタッフの予約管理、顧客情報管理（LINE連携、写真撮影枠管理）、勤怠管理、契約・制約管理など、事業運営の幅広い機能を備えています。

## 2. 技術スタック

- **Laravel**: 9.19
- **PHP**: 8.0.2 以上（Dockerfile では PHP 8.2-apache）
- **DB**: MySQL 5.7 以上 / MariaDB 10.3 以上（デフォルト接続）
- **フロントエンド**: Vue 3（Inertia.js）+ Vite
- **CSS**: Tailwind CSS 3.2.1
- **ビルドツール**: Vite 4.0.0 + npm
- **JS 主要ライブラリ**:
  - `@inertiajs/vue3` ^1.0.0（Inertia.js フレームワーク）
  - `chart.js` ^4.5.1（グラフ表示）
  - `@fullcalendar/vue3` ^6.1.19（カレンダー UI）
  - `fabric` ^6.9.1（キャンバス操作）
  - `qrcode` ^1.5.4（QR コード生成）
  - `swiper` ^12.0.3（スライドショー）

- **主要 PHP パッケージ**:
  - `inertiajs/inertia-laravel` ^0.6.8（Inertia.js バックエンド統合）
  - `laravel/sanctum` ^3.2（API 認証）
  - `laravel/socialite` *（OAuth連携）
  - `google/apiclient` *（Google Calendar API）
  - `intervention/image` ^3.11（画像処理）
  - `league/flysystem-aws-s3-v3` ^3.0（AWS S3）
  - `doctrine/dbal` ^4.4（スキーマ操作）
  - `zbateson/mail-mime-parser` ^3.0（メール解析）

- **開発用パッケージ**: Laravel Breeze, Laravel Debugbar, PHPUnit, Faker, Pint（コード整形）

## 3. ドメインモデル

**計50モデル以上** が定義されており、以下のドメインに分類できます：

### ユーザー・認証系
- `User`（スタッフ・管理者）
- `AttendanceRecord`（勤怠記録）
- `AttendanceBreak`（休憩記録）
- `AttendancePayrollSetting`（給与設定）
- `WorkAttribute`（業務属性・シフトパターン）
- `WorkAttributePatternTime`

### イベント・予約系
- `Event`（イベント情報）
- `EventTimeslot`（予約枠・時間帯）
- `EventReservation`（イベント予約）
- `EventImage`（イベント画像）
- `EventUtmTracking`（UTM流入元追跡）

### 顧客・成約系
- `Customer`（顧客情報）
- `CustomerNote`（顧客メモ）
- `CustomerTag`, `CustomerTagAssignment`（顧客タグ）
- `Contract`(成約・契約)
- `CustomerConstraint`（顧客制約・ご利用規約同意）
- `CustomerPhoto`（顧客写真）
- `CustomerLineContact`, `CustomerLineMessage`（LINE連携）
- `CustomerLineLinkToken`（LIFF連携トークン）

### 店舗・会場・スタッフ管理
- `Shop`（店舗情報）
- `Venue`（会場・撮影スタジオ）
- `PhotoStudio`（写真スタジオ）
- `PhotoSlot`（前撮り予約枠）
- `PhotoType`（写真種別）
- `ScheduleParticipant`（スケジュール参加者）
- `StaffSchedule`（スタッフスケジュール）

### コンテンツ・資料系
- `Document`（資料・カタログ）
- `DocumentImage`（資料内画像）
- `Slideshow`, `SlideshowImage`（スライドショー）
- `ConstraintTemplate`（制約テンプレート）
- `TimeslotTemplate`, `TimeslotTemplateSlot`（予約枠テンプレート）
- `Plan`（料金プラン）
- `CeremonyArea`（成人式エリア）

### 管理・ログ系
- `ActivityLog`（アクティビティログ）
- `BlockedIp`（ブロック IP）
- `Email`, `EmailAttachment`, `EmailThread`（メール管理）
- `LineUnknownInboundMessage`（不明な LINE 受信メッセージ）
- `GoogleCalendarEventSync`（Google Calendar 同期）
- `CompanyCalendarDay`（営業カレンダー）

### その他
- `ReservationNote`（予約メモ）
- `S3TestItem`（S3 テスト用）
- `UtmSource`（UTM ソース）

## 4. 機能一覧（ルート・コントローラーから読み取れる範囲）

### 公開機能（認証なし）
- **イベント詳細ページ** (`/event/{slug}`) — イベント情報・画像スライドショー表示
- **予約フォーム** — 日時選択、顧客情報入力、郵便番号から自動住所検索
- **資料請求フォーム** — 郵送/デジタル配信選択、お客様情報入力
- **お問い合わせフォーム** — 問い合わせ内容送信
- **郵便番号検索 API** (`/api/postal-code/search`) — 住所自動入力
- **資料表示** (`/document/{document}`) — PDF/カタログ表示

### 管理画面（認証必須）

#### イベント・予約管理
- イベント CRUD、基本情報編集、公開状態管理
- イベント画像管理（アップロード、ドラッグ&ドロップ並び替え、WebP変換）
- イベント CTA デザイン設定（ボタン位置・色）
- イベント LP 設定（LP 固有の配信先・ページデザイン）
- 予約一覧（日付グループ化表示）
- 予約詳細・編集（日時変更、顧客紐づけ、ステータス・割当て管理）
- 予約メモ管理
- 予約への LINE メッセージ機能
- 予約メール返信機能
- 入場チケット送信ステータス管理

#### 予約枠管理
- 予約枠 CRUD（日付・時間・定員）
- 定員調整ボタン（-1, +1, +5）
- 予約枠テンプレート（複数イベントで再利用）

#### 顧客管理（大規模機能）
- 顧客 CRUD
- 顧客検索・フィルタリング
- 顧客メモ管理
- 顧客タグ・タグ割り当て
- 成人式年度別フィルター
- 顧客追加情報フォーム（受け取り確認）
- 成約・契約管理（複数プラン対応）
- 写真撮影枠管理（前撮り）
- 顧客写真アップロード・S3 マイグレーション
- 顧客ご利用規約・制約管理（PDF 添付）
- 顧客 LINE 連携（LIFF 経由の友だち紐づけ）
- 顧客 LINE メッセージ送受信

#### 撮影・スタジオ管理
- 前撮り予約枠（PhotoSlot）CRUD
- スタジオ CRUD
- スケジュール管理（FullCalendar 統合）
- スケジュール経費カテゴリ予測 AI

#### その他店舗管理機能
- 店舗 CRUD
- スタッフ（ユーザー）CRUD
- スタッフログイン ID & パスワード管理
- 会場（Venue）CRUD
- 顧客タグ管理
- 制約テンプレート管理（ご利用規約テンプレート）
- スライドショー管理

#### 勤怠・給与管理
- 勤務時間記録（出勤・退勤・休憩開始終了）
- 暫定記録の編集・適用
- 履歴表示
- 承認フロー（承認・却下・一括処理）
- 勤怠ロール（勤怠管理者権限）
- 営業カレンダー管理
- 給与設定（時給・月給・ボーナス等）
- 給与シミュレーター
- 勤務属性・勤務パターン管理
- CSV エクスポート

#### ログ・セキュリティ
- アクティビティログ一覧・詳細（action_type, user_id, shop_id でフィルタリング）
- IP ブロック管理・ブロック解除
- ログイン失敗検知（24時間10回で自動ブロック）
- 変更前後の値（old_values / new_values）記録

#### その他
- Google Calendar 連携（OAuth、トークン更新）
- LINE Messaging API Webhook（POST `/webhook/line/messaging`）
- LINE LIFF（LINE ログイン、友だち紐づけ）
- UTM 分析 API（GAS 等からのデータ連携）
- SES メール送受信テスト
- 郵便番号 API テスト
- S3 アップロードテスト

### API エンドポイント
- `GET /api/user` — 認証ユーザー情報
- `GET /api/utm-analytics` — UTM 分析データ（X-Api-Key 認証）
- `GET /api/google-calendar/keep-token` — Google Calendar トークン更新

## 5. 画面構成

### ビューディレクトリ構造
```
resources/views/
├── app.blade.php              （Inertia.js ルートレイアウト）
├── welcome.blade.php          （ウェルカムページ）
├── mail/                       （メールテンプレート）
└── line/                       （LINE LIFF ビュー：LINE ログイン、友だち紐づけ画面）
```

### 主要フロントエンド構成
```
resources/js/
├── app.js                      （Inertia.js エントリーポイント）
├── bootstrap.js                （axios/その他初期化）
├── Components/
│   ├── Admin/                  （管理画面コンポーネント群）
│   ├── Attendance/             （勤怠管理コンポーネント）
│   ├── ActionButton.vue
│   ├── Modal.vue, Dropdown.vue
│   ├── StatCard.vue            （統計情報カード）
│   ├── TrendChart.vue          （トレンドグラフ）
│   ├── Slideshow.vue           （画像スライドショー）
│   ├── ConstraintBodyWithChecks.vue （制約チェック UI）
│   ├── Invoice.vue             （請求書表示）
│   ├── DigitalClock.vue        （デジタル時計）
│   └── QuickAccessCard.vue     （クイックアクセス）
├── Layouts/                    （ページレイアウト）
├── Pages/                      （各機能の Vue ページコンポーネント）
├── utils/                      （ユーティリティ関数）
├── constants/                  （定数定義）
└── vendor/                     （外部スクリプト）
```

**Inertia.js** により、Vue 3 コンポーネントが直接 Laravel コントローラーから渡されます。ルートビューは `resources/views/app.blade.php` で統一。

## 6. 最近の開発動向

最新のマイグレーションから、2026年2～4月の開発を読み取ると：

### 2026年2月～3月初旬（成約・勤怠機能）
- 2026-02-24: 契約（Contract）に soft delete 追加
- 2026-03-03: **勤怠機能の大規模追加**
  - `attendance_records`（出退勤記録）
  - `attendance_breaks`（休憩記録）
  - `users.attendance_role`（勤怠管理者権限）

### 2026年3月中旬（UTM・分析機能）
- 2026-03-12: UTM 分析機能
  - `event_reservations.utm_source`
  - `events.utm_analytics_enabled / utm_analytics_sort_order`

### 2026年3月中旬～下旬（給与・スケジュール管理）
- 2026-03-19: **給与・営業カレンダー機能**
  - `work_attributes`（業務属性・勤務種別）
  - `work_attribute_pattern_times`（勤務パターン）
  - `company_calendar_days`（営業カレンダー）
  - `attendance_payroll_settings`（給与設定）
  - `users.work_attribute_id`

- 2026-03-28: **成人式準備機能の強化**
  - `event_reservations.admin_assignee`（スタッフ割り当て）
  - `event_reservations.entrance_ticket_send_status`（チケット送信済み状況）
  - `customer_constraints.attachment`（制約添付ファイル）
  - `customers` に成人式準備フィールド

### 2026年3月29日（LINE 連携の大規模改善）
- LINE Messaging API の統一
  - `customer_line_contacts`（顧客 LINE 連携先）
  - `customer_line_messages`（メッセージ履歴）
  - `customer_line_link_tokens`（LIFF トークン）
  - `line_unknown_inbound_messages`（不明なメッセージ）
- **予約者向け LINE**: `event_reservations` に LINE メッセージ対応

### 2026年4月4日～5日（成人式＆LP機能）
- 2026-04-04: **卒業式・成人式対応**
  - `event_reservations.hakama_fields`（袴レンタル）
  - `event_reservations.graduation_ceremony_date`（卒業式日）
- 2026-04-05: **LP デザイン・CTA カスタマイズ**
  - `events.lp_design_fields`（LP デザイン用フィールド）

### 開発の傾向
**最新3週間は「成人式～卒業式シーズンに向けた機能強化」と「LINE 連携の刷新」が中心**。特に LINE メッセージ機能が大幅に拡張されており、顧客（Customer）と予約者（EventReservation）の両方で LINE 連携できるようになっています。

## 7. 業務ルール・独自制約

### ご利用規約（サンプル制約.md）
規約ファイルには、京呉服平田の成人式・振袖レンタル業務に固有の制約が定義されています：

#### キャンセル料金体系（重要）
| 期間 | キャンセル料 |
|------|-----------|
| 契約から8日以内 | クーリングオフ（無料） |
| 9～30日後 | 10% |
| 31～150日後 | 20% |
| 151日～貸出日31日前 | 50% |
| 貸出日30日以内、前撮り後 | 100% |

#### 重要な日程制限
- 商品見立て替え: **前撮り 3か月前まで**
- 前撮り日時変更: **3か月前まで**（1か月前の変更は美容師キャンセル料 ¥5,500）
- レンタル返却: **3営業日以内**（超過は 1 日につきレンタル料 10%）
- 残金支払い: **ご成約日から 10 営業日以内**
- お内金: ¥10,000（カード・現金払い以外）

#### 安心保障
- 振袖レンタル: ¥11,000、小物: ¥5,500、男性袴: ¥5,500
- 破損・汚れを保障（紛失・盗難・故意は除外）
- 前撮り＋成人式当日のみ適用

#### その他制約
- 支払方法: カード、現金、振込、信販ローン
- 国際情勢による入荷困難時は商品変更あり
- 成人式開催時刻発表後、お支度時間の変更・調整あり

### システムの制約処理
- `ConstraintTemplate` モデルで規約テンプレート管理
- `CustomerConstraint` で顧客ごとの規約同意・PDF 添付（2026-03-28 追加）
- 管理画面で規約の署名確認・管理

## 8. デプロイ・運用

### Docker ベース（deploy.sh より）
```dockerfile
FROM php:8.2-apache
- PHP 拡張: pdo_mysql, mbstring, zip, gd, intl
- Node.js + npm（フロントエンドビルド）
- Composer
- Apache mod_rewrite
- PHP 設定: upload_max_filesize 無制限, memory_limit -1
```

### デプロイパイプライン
1. Composer + npm CI（本番最適化）
2. npm run build（Vite ビルド）
3. php artisan storage:link（シンボリックリンク）
4. php artisan key:generate（必要に応じて）
5. キャッシュクリア

### 環境設定
- **DB**: MySQL（utf8mb4 デフォルト）
- **SESSION**: file（またはその他）
- **QUEUE**: sync（デフォルト）
- **FILESYSTEM**: 主に AWS S3
- **MAIL**: SMTP（SES 想定、テスト機能あり）
- **Google API**: Google Calendar OAuth（共通トークン + ユーザー個別）
- **LINE**: Messaging API（全店舗共通 1 チャネル） + LINEログイン LIFF
- **UTM 分析**: X-Api-Key トークン認証

## 9. 追加開発時の注意点

### 重要なアーキテクチャ特性
1. **Inertia.js フレームワーク**
   - ルート定義が Laravel 側、UI は Vue 3 コンポーネント
   - 新画面追加時は `resources/js/Pages/Admin/...` に Vue ファイル + ルート定義が必須
   - `Inertia::render()` で Vue コンポーネントにデータを渡す

2. **複雑なドメイン関係**
   - `Customer` ↔ `EventReservation` の両向き紐づけ
   - `CustomerLineContact` が顧客とイベント予約の両方に対応（2026-03-29 より）
   - 削除時の CASCADE / ON DELETE を要確認

3. **LINE 連携の二重化**
   - Messaging API（全店舗共通 1 チャネル）
   - LIFF（LINEログインチャネル別途、ID トークン検証必須）
   - Webhook 署名検証（`LogActivity` ミドルウェアで記録）

4. **セキュリティ・監査**
   - `ActivityLog` で全 CRUD を記録（old_values / new_values）
   - IP ブロック機構（ログイン失敗 10 回で自動ブロック）
   - CSRF トークン、Sanctum API 認証

5. **マイグレーション順序**
   - 外部キーが複雑に絡む（soft delete 含む）
   - 新規テーブル追加時は既存テーブルへの FK 参照を先に作成

6. **フロントエンド注意点**
   - Tailwind CSS で統一
   - FullCalendar（スケジュール表示）
   - Chart.js（グラフ）
   - Swiper（スライドショー）
   - これらの config / 依存関係を npm install 後に確認

7. **設定ファイル**
   - `config/line.php` — LINE 設定
   - `config/attendance.php` — 勤怠設定（新設）
   - `.env` に LINE トークン、Google API キー、UTM シークレット を要設定

8. **データベース考慮点**
   - `event_reservations` が巨大化する可能性（アーカイブ戦略検討）
   - `activity_logs` は定期削除推奨
   - `customers` に多数の 1:N リレーション（メモ、写真、タグ、制約）

9. **パフォーマンス**
   - 予約一覧の大量表示時はページネーション + インデックス確認
   - Google Calendar 同期（`GoogleCalendarEventSync`）は定期 cron で
   - UTM 分析 API は集計期間がデフォルト全期間（フィルタ推奨）

10. **運用・トラブル対応**
    - LINE メッセージ失敗時は `customer_line_messages.admin_read_at` で未読判定
    - 顧客 LINE リンク失敗時は `LINE_LIFF_ID` 及び `LINE_LOGIN_CHANNEL_ID` 確認
    - ログイン IP ブロック時は管理画面 > ログ管理 > ブロック解除ボタン

---

本システムは **成人式シーズンに向けた急速な拡張中** です。LINE 連携・勤怠管理・成人式向けカスタマイズが最近 3 週間で集中的に追加されたため、新規開発時は **これらの新機能との相互作用** を十分にテストしてください。
