<?php

// 未ログイン訪問者に渡すルートのみを列挙（Ziggy groups）
// ログイン済みは app.blade.php 側で全ルートを渡すため、ここは guest 専用
return [
    'groups' => [
        'guest' => [
            'login',
            'logout',
            'register',
            'password.*',       // request/email/reset/store/update/confirm
            'verification.*',
            'device.*',         // 端末登録（Auth画面が使用）
            'event.*',          // 公開LP4ルートのみに一致（events.*等は不一致を確認済み）
            'blade-lp.reserve',
            'api.postal-code.search',
            'document.*',       // 資料表示
            'sitemap',
            'dashboard',        // Welcome.vueのリンク用
        ],
    ],
];
