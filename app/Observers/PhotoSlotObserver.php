<?php

namespace App\Observers;

use App\Models\PhotoSlot;
use App\Models\StaffSchedule;
use App\Services\GoogleCalendarSyncService;
use Illuminate\Support\Facades\Log;

class PhotoSlotObserver
{
    public function __construct(protected GoogleCalendarSyncService $google) {}

    /**
     * customer_id（または日時・会場）の変更を検知して
     * 関連 StaffSchedule のタイトル更新 + Google再同期
     */
    public function updated(PhotoSlot $slot): void
    {
        $watch = ['customer_id', 'photo_studio_id', 'shoot_date', 'shoot_time'];
        if (!$slot->wasChanged($watch)) return;

        $sched = StaffSchedule::where('photo_slot_id', $slot->id)->first();
        if (!$sched) return;

        $slot->load('customer', 'studio');
        $studioName = $slot->studio ? trim($slot->studio->name) : '会場未定';
        $title = $slot->customer
            ? "[前撮り] {$slot->customer->name}（{$studioName}）"
            : "[前撮り] 未予約（{$studioName}）";

        $update = ['title' => $title];
        if ($slot->wasChanged(['shoot_date', 'shoot_time']) && $slot->shoot_date && $slot->shoot_time) {
            $start = \Carbon\Carbon::parse($slot->shoot_date . ' ' . $slot->shoot_time);
            $update['start_at'] = $start;
            $update['end_at'] = $start->copy()->addHour();
        }
        $sched->update($update);

        if (!$sched->sync_to_google_calendar) return;

        try {
            $sched->load('participantUsers.shops', 'photoSlot.studio', 'photoSlot.customer');
            $this->google->syncScheduleToShopCalendarsOnUpdate($sched);
        } catch (\Throwable $e) {
            Log::error('[PhotoSlotObserver] Google sync failed', [
                'slot_id' => $slot->id,
                'schedule_id' => $sched->id,
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
