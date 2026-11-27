<?php

/*
|--------------------------------------------------------------------------
| イベント LP のブランド（京呉服 好一／京呉服 平田）
|--------------------------------------------------------------------------
|
| LP のヘッダー・メニュー・SEO（title／og:site_name／JSON-LD）・Blade LP の電話番号を
| ここに集約する。HP のリニューアルで URL が変わったときは .env の BRAND_* を差し替えれば
| コードを変えずに反映できる（変更後は php artisan config:cache）。
|
| どのブランドで出すかは App\Support\LpBrand::forEvent() が決める
| （対象店舗の名前に「福井」を含むイベントは平田、それ以外は好一）。
|
| URL を空（null）にした項目は、LP のメニューにリンクを出さない。
*/

return [

    'default' => 'kouichi',

    // 対象店舗名にこの文字を含むイベントを平田（福井）として扱う
    'hirata_shop_keyword' => '福井',

    'brands' => [

        'kouichi' => [
            'key' => 'kouichi',
            'name' => '京呉服 好一',
            'description' => '岡山の振袖専門店',
            'logo' => '/storage/logo/logo_b.png',
            // 代表電話（岡山店）。tel: リンクにはハイフンなしを使う
            'tel' => '086-242-1529',
            'site_url' => env('BRAND_KOUICHI_SITE_URL', 'https://kyogofuku-kouichi.com/'),
            'shop_url' => env('BRAND_KOUICHI_SHOP_URL'),
            'rental_url' => env('BRAND_KOUICHI_RENTAL_URL'),
            'catalog_url' => env('BRAND_KOUICHI_CATALOG_URL', 'https://kyogofuku-kouichi.com/request/'),
            'instagram_url' => env('BRAND_KOUICHI_INSTAGRAM_URL', 'https://www.instagram.com/kouichi_furisode/'),
            'line_url' => env('BRAND_KOUICHI_LINE_URL', 'https://lin.ee/R7RUNlX'),
            // 終了したイベントから案内する通年の来店予約ページ（events.slug）
            'store_reservation_slug' => env('BRAND_KOUICHI_STORE_RESERVATION_SLUG', 'reservation_store_kouichi'),
        ],

        'hirata' => [
            'key' => 'hirata',
            'name' => '京呉服 平田',
            'description' => '福井の振袖専門店',
            'logo' => '/storage/logo/logo_hirata_b.png',
            // 代表電話（福井店）
            'tel' => '0776-34-1529',
            'site_url' => env('BRAND_HIRATA_SITE_URL', 'https://fukui.kyogofuku-hirata.jp/'),
            'shop_url' => env('BRAND_HIRATA_SHOP_URL'),
            'rental_url' => env('BRAND_HIRATA_RENTAL_URL'),
            'catalog_url' => env('BRAND_HIRATA_CATALOG_URL', 'https://fukui.kyogofuku-hirata.jp/request/'),
            'instagram_url' => env('BRAND_HIRATA_INSTAGRAM_URL', 'https://www.instagram.com/kyogofuku_hirata/'),
            // 平田の公式 LINE が別にある場合は .env の BRAND_HIRATA_LINE_URL で差し替える（現状は好一と同じ）
            'line_url' => env('BRAND_HIRATA_LINE_URL', 'https://lin.ee/R7RUNlX'),
            'store_reservation_slug' => env('BRAND_HIRATA_STORE_RESERVATION_SLUG', 'reservation_store_hirata'),
        ],

    ],

    // 通年の店舗来店予約ページ（Event 構造化データを出さない・説明文を店舗向けにする）の slug の頭
    'store_reservation_slug_prefix' => 'reservation_store',

];
