<?php

return [

    /*
    |--------------------------------------------------------------------------
    | 勤怠マニュアルURL
    |--------------------------------------------------------------------------
    | スタッフ用（打刻・履歴・仮登録）と管理者用（承認・勤怠管理含む）のマニュアルURL。
    | .env で ATTENDANCE_MANUAL_URL（スタッフ用）、ATTENDANCE_MANUAL_MANAGER_URL（管理者用）を設定可能。
    | 管理者用が未設定の場合はスタッフ用URLを使用します。
    */

    'manual_url' => env('ATTENDANCE_MANUAL_URL', ''),
    'manual_url_manager' => env('ATTENDANCE_MANUAL_MANAGER_URL', env('ATTENDANCE_MANUAL_URL', '')),
];
