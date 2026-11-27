<?php

/*
|--------------------------------------------------------------------------
| アプリが付ける・外すセキュリティ関連ヘッダー（App\Http\Middleware\SecurityHeaders）
|--------------------------------------------------------------------------
|
| サーバー（Apache）側で同じ設定をしたら、ここは無効にしてもよい。
*/

return [

    // HTTPS で受けたリクエストにだけ Strict-Transport-Security を付ける
    'hsts' => [
        'enabled' => env('SECURITY_HSTS_ENABLED', true),
        'max_age' => (int) env('SECURITY_HSTS_MAX_AGE', 31536000),
        // サブドメインまで強制するかは、全サブドメインが HTTPS になってから true にする
        'include_subdomains' => env('SECURITY_HSTS_INCLUDE_SUBDOMAINS', false),
    ],

    // PHP のバージョンを出す X-Powered-By を消す
    'remove_powered_by' => env('SECURITY_REMOVE_POWERED_BY', true),

];
