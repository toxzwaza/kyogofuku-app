<?php

/**
 * 顧客 1:1 LINE（Messaging API）は全店舗共通の 1 チャネル。.env で設定する。
 * LIFF は LINE ログインチャネルに紐づける（Messaging API チャネルには LIFF を追加できない）。
 * customers.shop_id は担当店舗として別途保持する。
 */
return [
    'messaging' => [
        'channel_secret' => env('LINE_MESSAGING_CHANNEL_SECRET'),
        'channel_access_token' => env('LINE_MESSAGING_CHANNEL_ACCESS_TOKEN'),
    ],
    'liff' => [
        /** LIFF アプリの ID（liff.line.me/{ここ}） */
        'id' => env('LINE_LIFF_ID', env('LINE_MESSAGING_LIFF_ID')),
        /**
         * あいさつメッセージ経由のセルフ紐付け LIFF アプリ ID。
         * 未設定時は通常の LIFF ID にフォールバックする。
         * 紐付け方式が異なるため別 LIFF アプリ（Endpoint URL = /line/liff/welcome）を推奨。
         */
        'welcome_id' => env('LINE_WELCOME_LIFF_ID'),
        /**
         * ID トークン検証用の client_id（LINE Developers の「LINEログイン」チャネル Basic settings の Channel ID）
         * 未設定時は後方互換で LINE_MESSAGING_CHANNEL_ID を参照（レガシー構成向け）
         */
        'login_channel_id' => env('LINE_LOGIN_CHANNEL_ID', env('LINE_MESSAGING_CHANNEL_ID')),
    ],

    /**
     * LIFF で初回連携が完了したとき、Messaging API でユーザーに送るあいさつ（改行可）
     * LINE_LINK_WELCOME_TEXT で .env から上書き可能
     */
    'link_welcome_text' => env('LINE_LINK_WELCOME_TEXT', "LINE連携が完了しました。\n\nこのトークからご質問・ご連絡をお送りいただけます。担当スタッフが確認いたします。"),

    /**
     * 公式アカウントの友だち追加 URL（サンクスメール・LIFF 内ボタン用）
     */
    'line_official_add_friend_url' => env('LINE_OFFICIAL_ADD_FRIEND_URL', 'https://lin.ee/R7RUNlX'),

    /**
     * サンクスメールに友だち追加 URL を併記する（false なら LIFF のみ案内）
     */
    'reservation_email_include_add_friend_url' => env('LINE_RESERVATION_EMAIL_INCLUDE_ADD_FRIEND_URL', true),
];
