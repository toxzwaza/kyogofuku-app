# Overview「LINE受信」ブロック追加 仕様書

- 作成日：2026-06-15
- 対象画面：管理画面オーバービュー（`admin.overview` / `/admin`）
- 区分：機能追加

---

## 1. 目的・背景

LINEでメッセージを受信した際は、現状LINEグループへ通知している。
しかし **休業日などに届いたメッセージ** は次の出勤日に対応するため、過去に遡って確認する必要がある。

そこで、出勤後に最初に開くオーバービュー画面で **未対応のLINE受信を一目で確認** できるブロックを設け、対応漏れを防ぐ。

## 2. 要件（確定仕様）

| 項目 | 仕様 |
|---|---|
| 配置 | 「最近の予約」ブロックの**左**に配置し、**左右均等（1:1）の2カラム** |
| 表示対象 | **未読メッセージのみ**（既読は非表示。対応済みは一覧から消える運用） |
| 店舗範囲 | **ログインユーザーの担当店舗のみ** |
| グループ化 | **お客様（LINEコンタクト）単位**でまとめる。複数行ではなく1行に集約 |
| 展開 | 行クリックで**アコーディオン展開**し、そのお客様の未読メッセージを時系列表示 |
| 参考 | 旧ダッシュボードの「LINE新着メッセージ」をベースに、新デザインシステムへ調整 |

### 標準装備
- カードヘッダーに**未読合計バッジ**
- **画像メッセージ**は `📷 画像` と明示（テキスト以外の種別）
- 受信時刻は**相対時間**表示（例「3時間前」「2日前」、7日以上前は日付表示）

### 今回見送り（将来の拡張候補）
- **未連携メッセージの警告表示**（`LineUnknownInboundMessage`＝顧客紐付け前の受信。最も見落としやすいため、別途追加すると漏れ防止が強化される）
- Overview上での既読化ボタン（現状は詳細画面遷移で既読化される運用で充足）
- 未読のみ/全件の切替トグル

## 3. 画面仕様

### レイアウト
```
┌──────── KPIカード / チャート（変更なし）────────┐

┌──── LINE受信 ────┐  ┌──── 最近の予約 ────┐  ← 新設の左右2カラム行
│ 未読 3 件          │  │ （既存内容を移設）   │
│ ▸ 田中花子 [予約][未読2]│ │                  │
│ ▸ 山田太郎 [顧客][未読1]│ │                  │
└──────────────────┘  └────────────────────┘

┌──── ヒートマップ ────┐ ┌─ 店舗別予約 ─┐  ← 既存（lg:grid-cols-3 のまま）
```

### お客様の見出し行（折りたたみ時）
- 左端：未読インジケータ（緑＝`uguisu-500` の縦バー）
- お客様名
- 種別バッジ：`予約`（primary）／`顧客`（neutral）
- 未読件数バッジ：`未読 n`（success）
- 最新メッセージのプレビュー（1行 truncate。画像は `📷 画像`）
- 右側：最新受信の相対時間 ＋ 展開キャレット（`ChevronDown`、展開時に180°回転）

### 展開部
- そのお客様の未読メッセージを**時系列（古い順）**でカード表示
- 各メッセージ：本文（画像は「画像メッセージ」表記）＋ 受信日時
- 末尾に導線リンク：「予約詳細を開く」／「顧客詳細を開く」
  - リンク先が存在する場合のみ表示（`reservation_id` または `customer_id` がある時）

### 並び順
- 最新メッセージを受信したお客様が先頭（最新受信順）

### 空状態
- 「未読のLINEメッセージはありません。」

## 4. データ仕様

### データソース
```
CustomerLineMessage（direction = inbound, admin_read_at = NULL で未読）
  └ contact: CustomerLineContact
       ├ customer（顧客）         … 顧客名・顧客詳細リンク
       └ eventReservation（予約） … 予約者名・予約詳細リンク
```

### 取得条件（OverviewController）
- `direction = inbound` かつ `admin_read_at IS NULL`（未読）
- コンタクトの `shop_id` がログインユーザーの担当店舗（`is_active` な店舗）に含まれる
- `id` 降順で最大 200 件を取得し、PHP側で `customer_line_contact_id` ごとにグループ化

### Inertia へ渡すデータ構造（`line_inbound`）
```php
[
  'unread_total' => int,        // 未読合計件数
  'groups' => [
    [
      'contact_id'      => int,
      'name'            => string,   // 顧客名 or 予約者名
      'label'           => string,   // コンタクトのラベル（お客様 等）
      'link_kind'       => 'customer' | 'reservation',
      'customer_id'     => int|null,
      'reservation_id'  => int|null,
      'unread_count'    => int,
      'latest_at'       => string|null,  // ISO8601
      'latest_preview'  => string,       // 最新本文の先頭120字
      'latest_is_image' => bool,
      'messages' => [                    // 時系列（古い順）
        [
          'id'           => int,
          'text'         => string,
          'is_image'     => bool,
          'message_type' => string|null,
          'created_at'   => string|null, // ISO8601
        ],
        // ...
      ],
    ],
    // ...
  ],
]
```

## 5. 既読化フロー（漏れ防止の肝）

本ブロックは**未読のみ**を表示する。既読化は既存ロジックを流用する。

1. Overview のお客様行 → 詳細導線（顧客／予約詳細）へ遷移
2. 詳細画面で LINE スレッドを開くと `CustomerLineMessageController::contactMessages`（および予約側の同等処理）が
   当該コンタクトの未読 `inbound` メッセージに `admin_read_at` を付与（既読化）
3. 次回 Overview 表示時、そのお客様は未読一覧から消える

→ **対応済みが自然に消えていく**ため、残存＝未対応として一目で把握できる。

## 6. 変更ファイル

| ファイル | 変更内容 |
|---|---|
| `app/Http/Controllers/Admin/OverviewController.php` | `CustomerLineMessage` の import 追加／担当店舗の未読受信を取得しお客様単位でグループ化／`line_inbound` を Inertia へ追加 |
| `resources/js/Pages/Admin/Overview.vue` | `line_inbound` props 追加／展開状態管理（`expandedGroups` / `toggleGroup` / `isExpanded`）／相対時間ヘルパー `fmtRelative`／「LINE受信」カード新設／レイアウトを左右2カラム（`lg:grid-cols-2`）に再構成し、ヒートマップ・店舗ランキングを別グリッドへ分離 |

### 使用UIコンポーネント
- `UiCard`（variant=default, padding=none）／`UiBadge`（success・primary・neutral）
- アイコン：`MessageCircle`・`ChevronDown`・`Image`（lucide-vue-next）
- カラートークン：`uguisu-500`（未読インジケータ）、`brand-*` セマンティックトークン

## 7. 検証

- フロントエンドビルド：`npm run build` 成功
- PHP構文：`php -l app/Http/Controllers/Admin/OverviewController.php` エラーなし
- 実機表示：担当店舗・未読データのある状態で `/admin` を開いて要確認

## 8. 留意点・今後

- 取得上限は直近 200 件（未読が極端に多い場合の保険）。通常運用では十分。
- 未連携メッセージ（`LineUnknownInboundMessage`）は本ブロックの対象外。見落とし防止を強化する場合は警告表示の追加を推奨。
