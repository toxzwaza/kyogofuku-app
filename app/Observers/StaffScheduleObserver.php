<?php

namespace App\Observers;

use App\Models\StaffSchedule;
use App\Services\GoogleCalendarSyncService;
use Illuminate\Support\Facades\Log;

class StaffScheduleObserver
{
    public function __construct(
        protected GoogleCalendarSyncService $syncService
    ) {}

    /**
     * スケジュール作成時のGoogleカレンダー同期は ScheduleController 等で
     * 参加者反映後に呼ぶ（Observer 発火時点では参加者が未紐づけのため）
     */
    public function created(StaffSchedule $schedule): void
    {
        Log::info('[GoogleCalendar] StaffSchedule created Observer 発火', [
            'schedule_id' => $schedule->id,
            'title' => $schedule->title,
        ]);
        // 参加者 sync 後に ScheduleController::store() から同期を呼ぶ
    }

    /**
     * スケジュール更新時にGoogleカレンダーへ同期（sync_to_google_calendar=true の場合のみ）
     */
    public function updated(StaffSchedule $schedule): void
    {
        if ($schedule->sync_to_google_calendar) {
            $this->syncService->syncScheduleToShopCalendarsOnUpdate($schedule);
        } else {
            $this->syncService->removeFromShopCalendarsIfSynced($schedule);
        }
    }

    /**
     * スケジュール削除前にGoogleカレンダーから削除
     * deleting を使用（deleted では cascade により sync レコードが既に削除済み）
     */
    public function deleting(StaffSchedule $schedule): void
    {
        // 削除時は同期済みレコードがあればGoogleカレンダーからも削除
        $this->syncService->deleteFromShopCalendars($schedule);
    }
}
