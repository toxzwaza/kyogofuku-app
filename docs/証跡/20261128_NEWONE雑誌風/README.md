# NEW ONE 最新ふりそで発表：雑誌の表紙風に差し替え（2026-11-28）

## 何を変えたか

- イベント LP（Inertia の `Event/Show`）で、イベント画像の中にある**前の NEW ONE 画像**（メディアライブラリ、S3 `public/media/`、alt「【福井】…」）を、
  店舗ごとの**雑誌の表紙風**（1080×1500 WebP）に差し替えて出す。DB は書き換えない（公開ページの props を作るときに差し替える）
- 好一（岡山）8ブランド・平田（福井）9ブランド（Allure は平田だけ）。並び順・alt は `config/lp_new_one.php`
- alt は「NEW ONE 最新ふりそで発表｜<ブランド>（京呉服 好一／京呉服 平田）」。「【福井】」の誤りはなくなる
- 店舗は `config/brands.php` と同じ決め方（対象店舗に「福井」を含むイベントは平田）
- 前の NEW ONE 画像が無いイベントは何も変わらない。差し込む位置は「前の NEW ONE 画像の先頭があった位置」。
  スライドショー・CTA ボタンの位置（何枚目の後ろか）は、枚数の変化に合わせて付け直す
- 画像はリポジトリの `public/images/lp/new-one/<店舗>/<ブランド>.webp`（素材 `08_NEWONE画像作り直し/出力/magazine` をそのまま複製。加工なし）

## 本番で差し替わるページ（2026-11-28 に公開ページで確認）

| ページ | 店舗 | 今の NEW ONE | 差し替え後 |
|---|---|---|---|
| `/event/reservation_YXQI0tXHmC`（振袖大祭典） | 好一 | 6枚（並び 17〜22） | 好一 8枚 |
| `/event/reservation_store_kouichi` | 好一 | 6枚（並び 8〜13） | 好一 8枚 |
| `/event/reservation_store_hirata` | 平田 | 6枚（並び 9〜14） | 平田 9枚 |

ほかのイベントでも、前の NEW ONE 画像（`config/lp_new_one.php` の `legacy_media_files` の7枚）のどれかが入っていれば同じように差し替わる。
新しく枠を出したいイベントには、管理画面でそのうち1枚を出したい位置に置く。

## 画面写し（手元・スマホ幅 390px）

本番の上の2ページと同じ画像の並びを使い捨て DB に作って撮った。

- 好一：`kouichi_newone_sp390.jpg`
- 平田：`hirata_newone_sp390.jpg`

## 本番に出す手順（村上さんの承認後）

画像はリポジトリに入っているので、**S3 へのアップロード・DB の変更・migration は要らない**。

```bash
ssh kyogofuku
PREV_HEAD=$(docker exec event_app git rev-parse HEAD); echo "PREV_HEAD=$PREV_HEAD"
docker exec event_app git status --short          # 何も出ないはず
docker exec event_app git pull origin main
docker exec event_app ls public/images/lp/new-one/kouichi public/images/lp/new-one/hirata   # 8 / 9 枚

docker exec event_app npm run build               # Show.vue の img に width/height を足したため
docker exec event_app php artisan optimize:clear
docker exec event_app php artisan route:cache
docker exec event_app php artisan view:cache
docker restart event_app
docker exec event_app php artisan config:clear
docker exec event_app php artisan config:cache    # config/lp_new_one.php を読み込む
```

確認：

```bash
curl -sI https://kyogofuku-event.com/images/lp/new-one/kouichi/edel.webp | head -1     # 200
curl -sI https://kyogofuku-event.com/images/lp/new-one/hirata/allure.webp | head -1     # 200
for s in reservation_YXQI0tXHmC reservation_store_kouichi reservation_store_hirata; do
  echo "$s: new=$(curl -s https://kyogofuku-event.com/event/$s | grep -o 'images/lp/new-one/[a-z]*/[a-z-]*\.webp' | sort -u | wc -l)  old=$(curl -s https://kyogofuku-event.com/event/$s | grep -c '【福井】')"
done   # new=8 / 8 / 9、old=0
```

ブラウザ（スマホ）で3ページを開き、NEW ONE の位置に新しい画像が並ぶこと、前後の画像・スライドショー・予約ボタンの位置が変わっていないことを見る。

## オン・オフ（コードを変えずに .env で）

| したいこと | .env | そのあと |
|---|---|---|
| あるブランドだけ止める | `LP_NEW_ONE_DISABLED="kouichi:edel,hirata:allure"` | `php artisan config:cache` |
| 店舗ごと止める（前の画像に戻る） | `LP_NEW_ONE_DISABLED="hirata:*"` | 同上 |
| 機能ごと止める（全イベントが前の画像に戻る） | `LP_NEW_ONE_ENABLED=false` | 同上 |
| 画像を S3 などに移す | `LP_NEW_ONE_BASE_URL=https://…/lp/new-one`（`<店舗>/<ブランド>.webp` の手前まで） | 同上 |

ブランドのキー：`kokonoe-nakamura-risa` `hanatsurezure-yoshiki-chisato` `anzai-seira-precious` `edel` `marune` `nuts` `sakurada-hiyori-aoi` `ourin-ranman` `allure`（平田だけ）。
ずっと止めるなら `config/lp_new_one.php` の `'enabled' => false` にしてデプロイする。

## 戻し方

- 急ぎ（見た目だけ前に戻す）：`.env` に `LP_NEW_ONE_ENABLED=false` → `docker exec event_app php artisan config:cache`。DB は変えていないので前の画像がそのまま出る
- コードごと戻す：

```bash
docker exec event_app git reset --hard $PREV_HEAD
docker exec event_app npm run build
docker exec event_app php artisan optimize:clear
docker restart event_app
docker exec event_app php artisan config:clear
docker exec event_app php artisan config:cache
```

## テスト

- 追加：`tests/Feature/EventLp/LpNewOneTest.php` 9件（好一 8枚・順番・alt、「【福井】」と前の画像が残らない、平田 9枚（Allure あり）、前の画像が無いイベントは変わらない、ブランドごとのオフ（.env・config）、店舗ごとオフ・機能オフで前の画像に戻る、スライドショー・CTA の位置の付け直し）
- `tests/Feature/EventLp` 36件すべて成功
- 全体：478 成功・33 失敗（33件はもとから落ちているもの〔PublicEventControllerTest・LINE 通知系・OverviewShopScopeTest など〕で、PR #19 のときと同じ。新しい失敗なし）
