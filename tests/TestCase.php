<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * テストで絶対に触ってはいけないDB名。
     * RefreshDatabase 等が本番同期データを破壊しないためのセーフティガード。
     */
    private const PROTECTED_DATABASES = [
        'localdb_prod',   // 本番同期データ
        'kyogofuku_db',   // 本番DB（直接接続した場合のため）
    ];

    protected function setUp(): void
    {
        // RefreshDatabase より先に Application を boot して config を読めるようにし、
        // 本番系DBがテスト対象なら即停止する（migrate:fresh される前にガード）。
        if (!$this->app) {
            $this->refreshApplication();
        }

        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if (in_array($database, self::PROTECTED_DATABASES, true)) {
            throw new \RuntimeException(
                "[テスト停止] 保護対象DB '{$database}' がテストで使われようとしました。"
                . " phpunit.xml の DB_DATABASE=localdb_test 設定を確認してください。"
            );
        }

        parent::setUp();
    }
}
