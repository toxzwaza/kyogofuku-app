<?php

namespace App\Console\Commands;

use App\Services\GoogleCalendarSyncService;
use Illuminate\Console\Command;

class KeepGoogleCalendarTokenAlive extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'google-calendar:keep-token-alive';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Google Calendar の refresh トークンを定期的に使用し、6ヶ月未使用による失効を防ぐ';

    /**
     * Execute the console command.
     */
    public function handle(GoogleCalendarSyncService $syncService): int
    {
        try {
            $result = $syncService->keepRefreshTokenAlive();

            if ($result) {
                $this->info('Google Calendar トークン維持処理が成功しました。');
                return Command::SUCCESS;
            }

            $this->warn('GOOGLE_CALENDAR_REFRESH_TOKEN が未設定のためスキップしました。');
            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Google Calendar トークン維持処理でエラーが発生しました: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
