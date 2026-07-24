# ポイント付与バッチ（referral:mature / referral:expire）cron セットアップ手順

友達紹介ポイント・平田ポイントの「成約1ヶ月後の自動付与」を動かすための cron 設定手順。

## 背景

- 付与は日次バッチ `referral:mature` が行う（`app/Console/Kernel.php` に `dailyAt('03:00')` で登録済み）。
- ただし本番は Docker 構成で、**Laravel スケジューラ＋supervisor は使わない方針**（Google Calendar も別方式。`docs/CRON_SETUP.md` 参照）。
- そのため、バッチを起動する cron を **VPS ホスト側に別途設定**する必要がある。設定しないと自動付与されない（管理画面の「ポイント反映」で手動付与は可能）。

## 対象コマンド

| コマンド | 内容 | 推奨時刻 |
|---|---|---|
| `referral:mature` | 成約から確定期間（既定1ヶ月）経過した紹介の確定＋ポイント付与、全成約者への平田ポイント付与 | 毎日 03:00 |
| `referral:expire` | 友達追加から期限（既定6ヶ月）経過した未成立紹介を失効 | 毎日 03:15 |

- どちらも **冪等**（付与済み・処理済みは二重処理しない）。多重起動しても事故らない。

## 手順（本番 VPS：ConoHa / Ubuntu）

### 1. Laravel コンテナ名を確認

本番へ接続し、Laravel（PHP）が動くコンテナ名を確認する。

```bash
ssh kyogofuku          # 本番接続（詳細は運用メモ参照）
docker ps              # Laravel アプリのコンテナ名を確認（例：event_app）
```

以下では例として **コンテナ名 `event_app`**、プロジェクトのログ出力先を `/path/to/kyogofuku-app/storage/logs/` とする。実際の値に置き換えること。

### 2. 動作確認（手動実行）

cron に入れる前に、コンテナ内で直接実行して成功するか確認する。

```bash
docker exec event_app php artisan referral:mature
# 例：「紹介確定: 0 件 / 対象 0 件、平田ポイント付与: 1 件 / 対象 1 件」等が出れば成功
```

### 3. cron を設定

**方式A：コマンド直接（推奨・現構成に合う）**

```bash
crontab -e
```

以下を追記：

```cron
# 京呉服平田 ポイント付与バッチ（紹介・平田の確定付与／期限切れ失効）
0 3 * * * docker exec event_app php artisan referral:mature >> /path/to/kyogofuku-app/storage/logs/referral.log 2>&1
15 3 * * * docker exec event_app php artisan referral:expire >> /path/to/kyogofuku-app/storage/logs/referral.log 2>&1
```

**方式B：Laravel スケジューラ（毎分 schedule:run）**

将来スケジュールを増やす場合はこちら。`Kernel::schedule()` の `dailyAt` に従って実行される。

```cron
* * * * * docker exec event_app php artisan schedule:run >> /dev/null 2>&1
```

> 方式A・Bは片方だけでよい。両方入れても冪等なので二重付与は起きないが、通常は方式A（またはB）のどちらか一方にする。

### 4. ログ確認

```bash
tail -f /path/to/kyogofuku-app/storage/logs/referral.log
```

## 補足

- **付与予定日**＝成約の確定検知日 ＋「確定までの月数」（管理画面「ポイント設定」で変更可・既定1ヶ月）。予定日を過ぎた最初の 03:00 バッチで付与される。
- **付与タイミングを変えたい**場合：`referral:mature` の実行時刻（cron の `0 3`）を調整。バッチ内の「1ヶ月後」判定は `maturation_months` 設定に従う。
- **cron が root 以外のユーザーで docker を叩けない**場合：`docker` グループへの所属、または `sudo` 付き実行を検討（環境のセキュリティポリシーに従う）。
- **ローカル開発環境**では cron 不要。検証時は手動で `php artisan referral:mature`（このリポジトリでは `/opt/homebrew/opt/php@8.2/bin/php artisan referral:mature`）を実行する。

## 関連

- コマンド定義：`app/Console/Commands/MatureReferrals.php`, `app/Console/Commands/ExpireReferrals.php`
- スケジュール登録：`app/Console/Kernel.php`
- Google Calendar トークン維持の cron（別方式）：`docs/CRON_SETUP.md`
