# ConoHa VPS (Ubuntu) で cron を設定する方法

Laravel のタスクスケジューラ（例: Google Calendar トークン維持処理の週1回実行）を ConoHa VPS の Ubuntu サーバーで定期実行するための設定手順です。

## 概要

Laravel のスケジューラは、**1分ごと** に `php artisan schedule:run` を実行する cron ジョブが必要です。`schedule:run` がその時点で実行すべきタスクを判断して実行します。

## 設定手順

### 1. cron を編集する

```bash
crontab -e
```

Web サーバーと同じユーザー（例: `www-data`）で実行する場合:

```bash
sudo crontab -u www-data -e
```

### 2. 以下の行を追加する

```cron
* * * * * cd /path/to/kyogofuku-app && php artisan schedule:run >> /dev/null 2>&1
```

`/path/to/kyogofuku-app` を Laravel プロジェクトの**実際の絶対パス**に置き換えてください。

- 例: `/var/www/kyogofuku-app`
- 例: `/home/deploy/kyogofuku-app`

### 3. ログを残したい場合

スケジューラの標準出力をファイルに残す場合:

```cron
* * * * * cd /path/to/kyogofuku-app && php artisan schedule:run >> /path/to/kyogofuku-app/storage/logs/scheduler.log 2>&1
```

**ログの出力先（2種類）:**

| ログ | ファイル | 内容 |
|------|----------|------|
| スケジューラの出力 | `storage/logs/scheduler.log` | どのコマンドが実行されたか（`schedule:run` の stdout） |
| アプリケーションログ | `storage/logs/laravel.log` | コマンド内の `Log::info()` など（例: トークン維持の成功/失敗） |

`google-calendar:keep-token-alive` はサービス層で `Log::info` / `Log::warning` / `Log::error` を使用しているため、`laravel.log` にも記録されます。

### 4. Docker コンテナの場合

コンテナ内にエディタがない場合、`crontab -e` の代わりに以下で登録できます:

```bash
echo '* * * * * cd /var/www/html && php artisan schedule:run >> /var/www/html/storage/logs/scheduler.log 2>&1' | crontab -
```

**重要:** コンテナ内で cron デーモンを起動する必要があります。コンテナの起動スクリプトや CMD に `cron -f` を追加してください（フォアグラウンドで動作させ、コンテナが終了しないようにする）。

## 補足・確認事項

### PHP のフルパスを使う場合

`php` コマンドがパスに含まれていない場合は、フルパスを指定します。

```bash
which php
# 例: /usr/bin/php
```

```cron
* * * * * cd /path/to/kyogofuku-app && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

### storage の書き込み権限

cron 実行ユーザーが `storage/logs` に書き込めるようにしておきます。

```bash
sudo chown -R www-data:www-data /path/to/kyogofuku-app/storage
# または
sudo chmod -R 775 /path/to/kyogofuku-app/storage
```

### 登録済みスケジュールの確認

```bash
cd /path/to/kyogofuku-app
php artisan schedule:list
```

`google-calendar:keep-token-alive` が表示されれば、スケジュール登録は問題ありません。

### 手動で動作確認

```bash
cd /path/to/kyogofuku-app
php artisan schedule:run
```

## 実行されるタスク

- **google-calendar:keep-token-alive**: 毎週月曜 2:00 に実行（Google Calendar の refresh トークン維持）

## 注意点

- **実行ユーザー**: Web サーバーと同じユーザー（例: `www-data`）で cron を動かすと、`.env` の読み込みや権限で問題が起きにくいです。
- **本番環境**: `APP_ENV=production` および `GOOGLE_CALENDAR_REFRESH_TOKEN` が正しく設定されていることを確認してください。
