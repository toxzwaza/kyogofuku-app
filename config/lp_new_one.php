<?php

/*
|--------------------------------------------------------------------------
| イベント LP の「NEW ONE 最新ふりそで発表」画像（雑誌の表紙風）
|--------------------------------------------------------------------------
|
| イベント画像の中に、前の NEW ONE 画像（メディアライブラリの 1080×1500、alt「【福井】…」）が
| 並んでいるところを、ここに書いた店舗（好一／平田）の新しい画像に差し替えて出す。
| 差し替えは App\Support\LpNewOne が公開ページの props を作るときに行う（DB は書き換えない）。
|
| - 店舗の決め方は config/brands.php と同じ（対象店舗に「福井」を含むイベントは平田、それ以外は好一）
| - 前の NEW ONE 画像が1枚もないイベントには何も足さない（今の見た目のまま）
| - 出すブランドが0件になった店舗は差し替えない（前の画像をそのまま出す）
|
| ■ ブランドごとのオン・オフ
|   ・コードで決める：下の items の 'enabled' を false にする
|   ・本番の .env で一時的に止める（コードを変えずに）：
|       LP_NEW_ONE_DISABLED="kouichi:edel,hirata:allure"   … 店舗:ブランドのキー（カンマ区切り）
|       LP_NEW_ONE_DISABLED="hirata:*"                     … 平田は全部止める（前の画像に戻る）
|       LP_NEW_ONE_ENABLED=false                            … 機能ごと止める（全イベントが前の画像に戻る）
|     .env を変えたあとは php artisan config:cache
|
| ■ 画像の置き場所
|   既定はリポジトリの public/images/lp/new-one/<店舗>/<ブランド>.webp（デプロイの git pull で本番に乗る）。
|   S3 などに移すときは LP_NEW_ONE_BASE_URL に「<店舗>/<ブランド>.webp」の手前までの URL を入れる。
|
| 画像は 1080×1500 の WebP（素材：HP・LP改修/成果物/08_NEWONE画像作り直し/出力/magazine、2026-11-28）。
| 画像そのものは加工しない（ブランドのガイドライン）。差し替えるときは同じ名前で置き換える。
*/

$disabled = array_values(array_filter(array_map('trim', explode(',', (string) env('LP_NEW_ONE_DISABLED', '')))));

return [

    'enabled' => (bool) env('LP_NEW_ONE_ENABLED', true),

    // 「店舗:ブランド」または「店舗:*」の並び（.env の LP_NEW_ONE_DISABLED）
    'disabled' => $disabled,

    // null のときは asset('images/lp/new-one')
    'base_url' => env('LP_NEW_ONE_BASE_URL'),

    // 画像の大きさ（img の width / height。読み込み中のずれを防ぐ）
    'width' => 1080,
    'height' => 1500,

    // alt の頭。後ろに「｜<ブランド名>（<店舗名>）」が付く
    'alt_prefix' => 'NEW ONE 最新ふりそで発表',

    /*
     | 前の NEW ONE 画像（メディアライブラリ。S3 の public/media/<この名前>）。
     | イベント画像の中でこれらが出てくる位置に、新しい画像をまとめて差し込み、これらは出さない。
     | 管理画面でイベントにこのどれか1枚を置けば、そのイベントのその位置に NEW ONE の枠が出る。
     */
    'legacy_media_files' => [
        'b06efac4-a241-41b1-bd10-6d5fd0de160a.webp', // 【福井】中村
        '373e6d8a-f87a-48a3-b6e3-b9728472d3f6.webp', // 【福井】なつみみ
        '073ebce5-8eff-4d4c-8a8b-20e30606495e.webp', // 【福井】安斉
        'b44a306f-b8ce-4635-8c1d-2119269a41d0.webp', // 【福井】あの
        '9f6ad667-3001-4cc2-b437-92828a67f548.webp', // 【福井】エーデル
        'ebf84495-593b-4c70-a529-df83c02ec442.webp', // 【福井】ナッツ
        '5b243151-6f0f-42f8-b95c-c860628664f8.webp', // 【福井】ノア
    ],

    /*
     | 店舗ごとの一覧（上から順に出す）。キーは config/brands.php の店舗キー。
     | 'key' は画像のファイル名（<key>.webp）と LP_NEW_ONE_DISABLED に使う。
     */
    'stores' => [

        'kouichi' => [
            'label' => '京呉服 好一',
            'items' => [
                ['key' => 'kokonoe-nakamura-risa', 'name' => '九重×中村里砂', 'enabled' => true],
                ['key' => 'hanatsurezure-yoshiki-chisato', 'name' => '華徒然×吉木千沙都', 'enabled' => true],
                ['key' => 'anzai-seira-precious', 'name' => '安斉星来×Precious', 'enabled' => true],
                ['key' => 'edel', 'name' => 'edel', 'enabled' => true],
                ['key' => 'marune', 'name' => 'marune', 'enabled' => true],
                ['key' => 'nuts', 'name' => 'nuts', 'enabled' => true],
                ['key' => 'sakurada-hiyori-aoi', 'name' => '桜田ひより×aoi', 'enabled' => true],
                ['key' => 'ourin-ranman', 'name' => '王林爛漫', 'enabled' => true],
            ],
        ],

        'hirata' => [
            'label' => '京呉服 平田',
            'items' => [
                ['key' => 'kokonoe-nakamura-risa', 'name' => '九重×中村里砂', 'enabled' => true],
                ['key' => 'hanatsurezure-yoshiki-chisato', 'name' => '華徒然×吉木千沙都', 'enabled' => true],
                ['key' => 'anzai-seira-precious', 'name' => '安斉星来×Precious', 'enabled' => true],
                ['key' => 'edel', 'name' => 'edel', 'enabled' => true],
                ['key' => 'marune', 'name' => 'marune', 'enabled' => true],
                ['key' => 'nuts', 'name' => 'nuts', 'enabled' => true],
                ['key' => 'sakurada-hiyori-aoi', 'name' => '桜田ひより×aoi', 'enabled' => true],
                ['key' => 'ourin-ranman', 'name' => '王林爛漫', 'enabled' => true],
                // Allure は平田だけ
                ['key' => 'allure', 'name' => 'Allure', 'enabled' => true],
            ],
        ],

    ],

];
