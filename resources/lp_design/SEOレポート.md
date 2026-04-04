# SEO分析レポート：振袖フェス in 岡山 LP

**対象ファイル：** Complete/ 配下の index.html / style.css / reserve.html / reserve.css  
**作成日：** 2026-04-05  
**最終更新：** 2026-04-05（SEO対応7項目を実施し、レポートを更新）  
**担当：** 広報部門

---

## 1. 実施済み事項

### title / meta description

| ページ | title | description |
|--------|-------|-------------|
| index.html | ゴールデンウィーク振袖フェス in 岡山 \| 京呉服平田 | GW限定！振袖フェス in 岡山。3,000点以上の振袖から運命の一着を。購入プラン8,800円～、レンタルプラン6,600円～。4/25・26、5/2～4開催。 |
| reserve.html | WEB予約 \| 振袖フェス in 岡山 \| 京呉服平田 | 振袖フェス in 岡山のWEB予約フォーム。岡山プラザホテル（4/25・26）・CONVEX岡山（5/2〜4）の会場・日時を選んで簡単予約。来場特典あり。 |

- 両ページとも主要キーワード（振袖フェス・岡山・GW・価格）を含む適切なtitle/descriptionを設定済み

### OGP / Twitterカード（対応済み）

両ページに以下を実装済み：

| メタタグ | index.html | reserve.html |
|----------|------------|--------------|
| og:title | ゴールデンウィーク振袖フェス in 岡山 \| 京呉服平田 | WEB予約 \| 振袖フェス in 岡山 |
| og:description | 設定済み | 設定済み |
| og:image | メインビジュアル画像（S3） | メインビジュアル画像（S3） |
| og:type | website | website |
| og:site_name | 京呉服平田 | 京呉服平田 |
| og:locale | ja_JP | — |
| twitter:card | summary_large_image | summary_large_image |
| twitter:title | 設定済み | — |
| twitter:description | 設定済み | — |
| twitter:image | 設定済み | — |

### 構造化データ JSON-LD（対応済み）

index.html に以下2つのJSON-LDを実装済み：

**Event スキーマ：**
```
name: ゴールデンウィーク振袖フェス in 岡山
startDate: 2026-04-25
endDate: 2026-05-04
eventStatus: EventScheduled
eventAttendanceMode: OfflineEventAttendanceMode
location[0]: 岡山プラザホテル 4F烏城の間（住所付き）
location[1]: CONVEX岡山 2F国際会議場（住所・郵便番号付き）
organizer: 京呉服平田（telephone付き）
offers[0]: 購入プラン 8,800円（JPY）
offers[1]: レンタルプラン 6,600円（JPY）
image: メインビジュアル画像URL
```

**Organization スキーマ：**
```
name: 京呉服平田
alternateName: edel by 京呉服平田
telephone: 0120-041-529
address: 岡山県
```

### 見出し構造（h1 / h2 / h3）

- h1 はページ内に1つのみ（適切）
- h2 で各セクション（来場特典・NO.1・コレクション・プラン・来場の流れ・会場・予約）を分類
- 会場名・プラン名に h3 を使用

### 画像最適化

- 全画像が **WebP形式** で軽量化済み
- ファーストビュー以外の画像に `loading="lazy"` を設定済み
- 全画像に `alt` 属性を付与済み
- ヒーロー画像に `fetchpriority="high"` + `width`/`height` 属性を設定（LCP最適化・CLS防止）

### パフォーマンス

- Google Fonts に `rel="preconnect"` を設定（DNS解決を事前実行）
- フォントウェイトを必要最小限に削減（Noto Sans JP: 400/700/900、Zen Old Mincho: 400/700/900）
- 未使用フォントファミリー（Noto Serif JP）を読み込みURLから除去
- CSS は外部ファイル化（キャッシュ可能）
- JavaScript はbody末尾に配置（レンダーブロッキング最小化）
- `requestAnimationFrame` によるスクロール処理の最適化
- `IntersectionObserver` による遅延アニメーション

### モバイル対応

- `viewport` メタタグ設定済み
- 3段階のレスポンシブ対応（480px / 768px / 1024px）
- モバイル固定CTAボタン実装
- タッチ操作に対応したボタンサイズ

### アクセシビリティ（対応済み）

| 項目 | 対応内容 |
|------|----------|
| セマンティックHTML | `<header>`, `<main>`, `<footer>`, `<section>`, `<article>` を適切に使用 |
| スキップリンク | `<a href="#main-content" class="skip-link">メインコンテンツへ</a>` をbody先頭に配置。Tab キーで表示される |
| main要素 | `<main id="main-content">` で来場特典〜予約セクションを囲む |
| aria-label | 固定CTA（WEB予約・電話予約）、予約セクションCTAにaria-labelを設定 |
| aria-hidden | 桜パーティクル等の装飾要素に設定 |
| フォーム構造化 | `<fieldset>`, `<legend>`, `required` 属性でフォームを構造化 |

### 内部リンク

- index.html → reserve.html へ複数のCTAリンク配置
- reserve.html → index.html への戻るリンク配置
- ページ内アンカーリンク（#reservation, #plans, #venues）によるスムーズスクロール

---

## 2. 未実施・今後の検討事項

### 本番公開時に対応が必要

#### (A) canonical タグの実装

URLの正規化が未設定。本番ドメイン確定後に実装が必要。

```html
<!-- index.html -->
<link rel="canonical" href="https://（本番ドメイン）/">
<!-- reserve.html -->
<link rel="canonical" href="https://（本番ドメイン）/reserve.html">
```

また、OGPの `og:url` も同時に設定すること。

#### (B) robots.txt / sitemap.xml

```
robots.txt
├── User-agent: *
├── Allow: /
└── Sitemap: https://（ドメイン）/sitemap.xml

sitemap.xml
├── index.html（priority: 1.0）
└── reserve.html（priority: 0.8）
```

### 追加改善（任意）

#### (C) Core Web Vitals の追加最適化

| 指標 | 現状 | 追加改善案 |
|------|------|-----------|
| LCP | ヒーロー画像に fetchpriority="high" 設定済み | プリロードヒント `<link rel="preload">` の追加検討 |
| CLS | ヒーロー画像に width/height 設定済み | その他画像にも width/height 明示を検討 |
| INP | JSアニメーションが多い | パーティクル生成を `requestIdleCallback` で遅延実行 |

#### (D) Critical CSS の分離

ファーストビュー表示に必要なCSSをインラインで埋め込み、残りを非同期読み込みにする。

**効果：** FCP（First Contentful Paint）の短縮

#### (E) 見出し階層の補強

一部セクションで h2 の直下に h3 を使わず div のみになっている箇所がある。
プラン名やサービス名を h3/h4 で構造化すると、セマンティック評価が向上する。

#### (F) アクセシビリティ追加強化

| 項目 | 改善案 |
|------|--------|
| フォームエラー通知 | `aria-live="polite"` リージョンでスクリーンリーダー対応 |
| カラーコントラスト | `var(--text-light)` (#9b8a96) のコントラスト比を確認（WCAG AA基準: 4.5:1） |

#### (G) パンくずリスト

2ページ構成のため優先度は低いが、構造化データ（BreadcrumbList）と併せて実装すると検索結果の表示が改善する。

#### (H) OGP画像の最適化

現在メインビジュアル画像（1080x1361px）をog:imageに使用しているが、SNSでの表示は1200x630pxが推奨。
専用のOGP画像を作成すると、シェア時の表示品質が向上する。

---

## 3. 実施状況サマリー

| カテゴリ | 状態 | 備考 |
|----------|------|------|
| title / description | **完了** | 両ページ設定済み |
| 見出し構造 | **完了** | 一部h3/h4の補強推奨 |
| 画像最適化 | **完了** | WebP・lazy・alt・fetchpriority対応 |
| 構造化データ | **完了** | Event + Organization JSON-LD実装済み |
| OGP / SNS対応 | **完了** | 両ページにog:* / twitter:*設定済み |
| canonical | 未実施 | 本番ドメイン確定後に設定 |
| robots / sitemap | 未実施 | 本番公開時に設定 |
| パフォーマンス | **完了** | fetchpriority・フォント削減・JS最適化済み |
| モバイル対応 | **完了** | 3段階レスポンシブ対応済み |
| アクセシビリティ | **完了** | スキップリンク・aria-label・main要素追加済み |
| 内部リンク | **完了** | CTA・アンカーリンク配置済み |
| Core Web Vitals | **概ね完了** | LCP・CLS対応済み。INPは追加改善余地あり |

---

## 4. 対応履歴

### 2026-04-05 SEO対応（7項目実施）

| # | 対応項目 | 対象ファイル | 内容 |
|---|----------|-------------|------|
| 1 | 構造化データ（JSON-LD） | index.html | Event スキーマ（会場2箇所・プラン2種の offers 含む）+ Organization スキーマを `<head>` に追加 |
| 2 | OGP / Twitterカード | index.html, reserve.html | og:title / og:description / og:image / og:type / og:site_name / og:locale / twitter:card / twitter:title / twitter:description / twitter:image を追加 |
| 3 | reserve.html の description | reserve.html | `<meta name="description">` を追加 |
| 4 | ヒーロー画像の最適化 | index.html | `fetchpriority="high"` + `width="1080"` + `height="1361"` を追加（LCP改善・CLS防止） |
| 5 | フォントウェイト削減 | index.html, reserve.html | Noto Sans JP: 300/500を除去（400/700/900のみ）、Noto Serif JPを読み込みから除去。2ファミリー×3ウェイト=6ファイルに削減 |
| 6 | スキップリンク | index.html, style.css | `<a href="#main-content" class="skip-link">` をbody先頭に追加。CSSで通常時非表示・Tab フォーカス時に表示 |
| 7 | aria-label | index.html | 固定CTA 2箇所 + 予約セクションCTA 2箇所にaria-labelを追加。`<main id="main-content">` でコンテンツ領域を明示 |

---

## 5. 残タスク（本番公開時）

1. **canonical タグ** — 本番ドメイン確定後に両ページに `<link rel="canonical">` と `og:url` を設定
2. **robots.txt** — サーバールートに配置
3. **sitemap.xml** — 2ページ分を記述しサーバールートに配置
4. **OGP専用画像** — 1200x630pxのシェア用画像を作成し og:image を差し替え（任意）
5. **Google Search Console** — 本番公開後にサイトを登録し、インデックス状況を監視
