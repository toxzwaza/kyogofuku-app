# UTM 流入経路分析 API リファレンス

イベント予約の流入経路（utm_source）を取得するための API です。Google Apps Script（GAS）等からスプレッドシートへ計測・分析データを転記する用途を想定しています。

## エンドポイント

| 項目 | 内容 |
|------|------|
| URL | `GET {APP_URL}/api/utm-analytics` |
| 例 | `https://example.com/api/utm-analytics` |

## 認証

認証が必須です。次のいずれかの方法で `.env` の `UTM_ANALYTICS_API_SECRET` を送信してください。

| 方法 | 指定箇所 |
|------|----------|
| ヘッダー | `X-Api-Key: {UTM_ANALYTICS_API_SECRET}` |
| クエリパラメータ | `?token={UTM_ANALYTICS_API_SECRET}` |

**シークレットの設定**

`.env` に以下を追加し、任意の文字列を設定します。

```
UTM_ANALYTICS_API_SECRET=あなたのシークレット
```

例: `openssl rand -hex 32` で生成した値など。

## 返却対象イベントの設定

API で返却するイベントは、管理画面のイベント詳細から「UTM分析APIに含める」チェックボックスを ON にしたもののみです。

- イベント一覧 → 対象イベントをクリック → 基本情報の「編集」→「UTM分析APIに含める」にチェック → 更新

返却順は管理画面の「API並び順」（イベント管理 > API並び順）で設定した順です。未設定のイベントは末尾、同順は作成日の降順です。

## クエリパラメータ（オプション）

集計期間を絞り込む場合に使用します。

| パラメータ | 型 | 説明 |
|------------|-----|------|
| `from_date` | string | 集計開始日（`Y-m-d` 形式、例: `2026-01-01`） |
| `to_date` | string | 集計終了日（`Y-m-d` 形式、例: `2026-03-31`） |

指定しない場合は全期間が対象です。

## レスポンス

### 成功時（200 OK）

```json
{
  "success": true,
  "total_conversion": 150,
  "events": [
    {
      "event_id": 1,
      "event_title": "〇〇イベント",
      "total_count": 80,
      "sources": [
        { "source": "google", "count": 45 },
        { "source": "LINE", "count": 35 }
      ]
    },
    {
      "event_id": 2,
      "event_title": "△△イベント",
      "total_count": 70,
      "sources": [
        { "source": "NONE", "count": 40 },
        { "source": "（未計測）", "count": 30 }
      ]
    }
  ]
}
```

| フィールド | 型 | 説明 |
|------------|-----|------|
| `success` | boolean | 成功時は `true` |
| `total_conversion` | integer | 対象イベント全体の予約総数（キャンセル除外） |
| `events` | array | 「UTM分析APIに含める」が ON のイベント一覧（作成日降順） |
| `events[].event_id` | integer | イベント ID |
| `events[].event_title` | string | イベントタイトル |
| `events[].total_count` | integer | そのイベントの予約数 |
| `events[].sources` | array | 流入元（utm_source）ごとの内訳 |
| `events[].sources[].source` | string | 流入元名（未計測: `（未計測）`、UTM 未付与: `NONE`） |
| `events[].sources[].count` | integer | 件数 |

### エラー時

| ステータス | 内容 |
|------------|------|
| 401 Unauthorized | 認証失敗（トークン不正または未送信） |
| 503 Service Unavailable | `UTM_ANALYTICS_API_SECRET` が未設定 |

```json
{
  "success": false,
  "message": "Unauthorized"
}
```

## リクエスト例

### cURL

```bash
# ヘッダーで認証
curl -H "X-Api-Key: あなたのシークレット" \
  "https://example.com/api/utm-analytics"

# クエリパラメータで認証
curl "https://example.com/api/utm-analytics?token=あなたのシークレット"

# 期間指定
curl "https://example.com/api/utm-analytics?token=あなたのシークレット&from_date=2026-01-01&to_date=2026-03-31"
```

### Google Apps Script（GAS）の利用例

デフォルトでイベント一覧と予約総数を表示し、イベント行をクリックすると utm_source ごとの内訳を表示する想定です。

```javascript
function fetchUtmAnalytics() {
  const baseUrl = 'https://example.com';  // APP_URL に置き換え
  const token = PropertiesService.getScriptProperties().getProperty('UTM_ANALYTICS_API_SECRET');

  const url = baseUrl + '/api/utm-analytics?token=' + encodeURIComponent(token);
  const response = UrlFetchApp.fetch(url);
  const data = JSON.parse(response.getContentText());

  if (!data.success) {
    throw new Error(data.message || 'API エラー');
  }

  const sheet = SpreadsheetApp.getActiveSpreadsheet().getActiveSheet();
  sheet.clear();

  // デフォルト表示: イベント一覧と予約総数
  sheet.getRange(1, 1).setValue('イベント');
  sheet.getRange(1, 2).setValue('予約総数');
  sheet.getRange(2, 1).setValue('（合計）');
  sheet.getRange(2, 2).setValue(data.total_conversion);

  data.events.forEach(function(event, i) {
    sheet.getRange(i + 3, 1).setValue(event.event_title);
    sheet.getRange(i + 3, 2).setValue(event.total_count);
    // クリック時に event.sources を使って utm_source 別の内訳を表示する処理を実装
  });

  return data;
}
```

**クリックで utm_source 内訳を表示する例**

- A 列のイベント名をクリックした際に、そのイベントの `sources` 配列を別シートやダイアログで表示する処理を GAS で実装します。
- `data.events` を保持しておき、クリックされた行の `event_id` に対応する `event.sources` を参照して内訳を表示します。

**GAS で ScriptProperties にトークンを設定する**

1. スクリプトエディタで「プロジェクトの設定」を開く
2. 「スクリプト プロパティ」の行の「スクリプト プロパティを追加」をクリック
3. プロパティ名: `UTM_ANALYTICS_API_SECRET`、値: `.env` の値

## 備考

- 集計対象は `event_reservations` テーブルの `cancel_flg = false` のレコードです
- `utm_source` は予約時点で URL パラメータまたはセッションから取得した値を保存しています
- 管理画面からの手動登録予約は `utm_source` が null となり、レスポンスでは `（未計測）` と集約されます
- 返却イベントは `events.utm_analytics_enabled = true` のもののみです
- 返却順は `utm_analytics_sort_order` で管理（イベント管理 > API並び順でドラッグ＆ドロップで変更）
