<?php

/*
 * 名簿（成人式 DM 名簿）。設計は taskboard page-28。
 */
return [
    // 画面が使うデータ：'dummy'＝画面の中の架空データ（既定）／'db'＝この kyogofuku-app の DB の meibo_* の表。
    // 切り替えたら php artisan config:cache。戻すときは dummy にして同じく config:cache するだけ。
    // （設計の古い呼び名 'http' も 'db' として扱う）
    'data_source' => env('MEIBO_DATA_SOURCE', 'dummy'),

    // 全選択で一度に選べる上限
    'select_limit' => 50000,
];
