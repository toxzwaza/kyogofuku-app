<?php

namespace App\Jobs;

use App\Models\StaffSchedule;
use App\Services\GoogleCalendarSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncToGoogleCalendarJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public StaffSchedule $schedule,
        public string $action = 'sync'
    ) {}

    /**
     * Execute the job.
     */
    public function handle(GoogleCalendarSyncService $syncService): void
    {
        if ($this->action === 'sync') {
            $syncService->syncScheduleToShopCalendarsOnUpdate($this->schedule);
        }
    }
}
