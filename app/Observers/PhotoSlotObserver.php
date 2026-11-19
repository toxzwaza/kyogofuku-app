<?php

namespace App\Observers;

use App\Models\PhotoSlot;
use App\Models\StaffSchedule;
use App\Services\GoogleCalendarSyncService;
use App\Services\PhotoSlot\PhotoSlotBookingService;
use App\Services\PhotoSlot\PhotoSlotScheduleService;
use Illuminate\Support\Facades\Log;

class PhotoSlotObserver
{
    public function __construct(
        protected GoogleCalendarSyncService $google,
        protected PhotoSlotScheduleService $schedules,
    ) {}

    /**
     * お客様・スタジオ・日時・詳細未決定が変わったら、予定（30分）とカレンダーを合わせる。
     * お客様が空になったら予定を消す（どの画面から外しても同じになるように）。
     * 予約のサービスの処理中は、サービスが最後にまとめて合わせるので何もしない。
     */
    public function updated(PhotoSlot $slot): void
    {
        if (PhotoSlotBookingService::isSuspended()) {
            return;
        }
        $watch = ['customer_id', 'photo_studio_id', 'shoot_date', 'shoot_time', 'details_undecided'];
        if (! $slot->wasChanged($watch)) {
            return;
        }

        try {
            $this->schedules->syncFor($slot);
        } catch (\Throwable $e) {
            Log::error('[PhotoSlotObserver] 予定の同期に失敗', [
                'slot_id' => $slot->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * PhotoSlot 削除前、関連 StaffSchedule の Google イベントを削除し、
     * StaffSchedule 本体も削除する。
     * deleting で実行しないと FK nullOnDelete で先に photo_slot_id=NULL になり
     * 関連 StaffSchedule を見失う。
     */
    public function deleting(PhotoSlot $slot): void
    {
        $sched = StaffSchedule::where('photo_slot_id', $slot->id)->first();
        if (!$sched) return;

        try {
            $this->google->deleteFromShopCalendars($sched);
        } catch (\Throwable $e) {
            Log::error('[PhotoSlotObserver] Google delete failed', [
                'slot_id' => $slot->id,
                'schedule_id' => $sched->id,
                'error' => $e->getMessage(),
            ]);
        }

        $sched->delete();
    }
}
