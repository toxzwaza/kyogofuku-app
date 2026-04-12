<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
        'scheme' => 'https',
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // UTM 流入経路分析 API 用（GAS 等からアクセス時の認証）
    'utm_analytics_api_secret' => env('UTM_ANALYTICS_API_SECRET'),

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
        // 共通トークン（全スタッフで使用、.env で設定）
        'calendar_refresh_token' => env('GOOGLE_CALENDAR_REFRESH_TOKEN'),
        // トークン維持エンドポイント用の認証トークン（Python/cron からアクセス時に使用）
        'calendar_keep_token_secret' => env('GOOGLE_CALENDAR_KEEP_TOKEN_SECRET'),
        // 公開フォームからの予約時に自動作成する staff_schedules.user_id（未設定時は自動作成をスキップ）
        'calendar_reservation_owner_user_id' => env('GOOGLE_CALENDAR_RESERVATION_OWNER_USER_ID'),
        // ローカル環境で SSL 証明書エラー (cURL error 60) を回避（本番では true 推奨）
        'guzzle' => [
            'verify' => env('APP_ENV') !== 'local',
        ],
    ],

    'anthropic' => [
        'api_key' => env('ANTHROPIC_API_KEY'),
        'model' => env('ANTHROPIC_MODEL', 'claude-sonnet-4-20250514'),
    ],

    'nl_api' => [
        'secret' => env('NL_API_SECRET'),
    ],

];
