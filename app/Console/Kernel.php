<?php

namespace App\Console;

use Illuminate\Console\Events\CommandStarting;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Event;

class Kernel extends ConsoleKernel
{
    /**
     * 本番同期データを破壊から守る対象DB名。
     */
    private const PROTECTED_DATABASES = [
        'localdb_prod',
        'kyogofuku_db',
    ];

    /**
     * 上記DBに対しては実行を拒否する破壊的コマンド。
     */
    private const DESTRUCTIVE_COMMANDS = [
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'db:wipe',
    ];

    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // Google Calendar トークン維持は Python + HTTP 方式を使用（docs/CRON_SETUP.md 参照）
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');

        // 破壊的コマンドが本番同期DBに対して走らないようにガード
        Event::listen(CommandStarting::class, function (CommandStarting $event) {
            if (!in_array($event->command, self::DESTRUCTIVE_COMMANDS, true)) {
                return;
            }
            $connection = config('database.default');
            $database = config("database.connections.{$connection}.database");
            if (in_array($database, self::PROTECTED_DATABASES, true)) {
                throw new \RuntimeException(
                    "[Artisan 停止] 破壊的コマンド '{$event->command}' を保護対象DB '{$database}' に対して実行しようとしました。"
                    . " 別DBに切り替えてから実行してください。"
                );
            }
        });
    }
}
