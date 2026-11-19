<?php

namespace App\Services\PhotoSlot;

use App\Models\PhotoSlot;
use App\Models\StaffSchedule;
use App\Services\GoogleCalendarSyncService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * 前撮りの予約とスタッフの予定（＋Googleカレンダー）を合わせる。
 * 予定は「お客様が入っている日時の決まった席」にだけ作る（30分）。空き枠・詳細未決定には作らない。
 * 何度呼んでも同じ結果になる（変わったところだけ直す）。
 */
class PhotoSlotScheduleService
{
    public const DURATION_MINUTES = 30;

    public function __construct(private GoogleCalendarSyncService $google)
    {
    }

    public function syncFor(PhotoSlot $slot, ?int $actorUserId = null): void
    {
        $slot = PhotoSlot::with(['customer', 'studio', 'shops.users'])->find($slot->id);
        $schedule = $slot ? StaffSchedule::where('photo_slot_id', $slot->id)->first() : null;

        if (! $slot || ! $this->needsSchedule($slot)) {
            if ($schedule) {
                // StaffScheduleObserver@deleting が Google の予定も消す
                $schedule->delete();
            }

            return;
        }

        $studioName = $slot->studio ? trim($slot->studio->name) : '会場未定';
        $start = Carbon::parse(Carbon::parse($slot->shoot_date)->toDateString().' '.substr($slot->shoot_time, 0, 5));
        $attrs = [
            'title' => "[前撮り] {$slot->customer->name}（{$studioName}）",
            'start_at' => $start,
            'end_at' => $start->copy()->addMinutes(self::DURATION_MINUTES),
            'all_day' => false,
        ];
        $participantIds = $slot->shops->flatMap(fn ($shop) => $shop->users->pluck('id'))->unique()->sort()->values()->all();

        if (! $schedule) {
            $creatorId = $actorUserId ?? auth()->id() ?? ($participantIds[0] ?? null);
            if (! $creatorId) {
                Log::warning('[PhotoSlotSchedule] 予定の作成者が決まらないため予定を作りません', ['slot_id' => $slot->id]);

                return;
            }
            $schedule = StaffSchedule::create($attrs + [
                'user_id' => $creatorId,
                'photo_slot_id' => $slot->id,
                'is_public' => true,
                'sync_to_google_calendar' => true,
            ]);
            if ($participantIds) {
                $schedule->participantUsers()->sync($participantIds);
            }
            $this->safely(fn () => $this->google->syncScheduleToShopCalendars($schedule), $slot, $schedule);

            return;
        }

        $currentIds = $schedule->participantUsers()->pluck('users.id')->sort()->values()->all();
        $participantsChanged = $currentIds !== $participantIds;
        if ($participantsChanged) {
            $schedule->participantUsers()->sync($participantIds);
        }

        $schedule->fill($attrs);
        if ($schedule->isDirty()) {
            // StaffScheduleObserver@updated が送り先の作り直しと送り直しをする
            $schedule->save();
        } elseif ($participantsChanged && $schedule->sync_to_google_calendar) {
            $this->safely(fn () => $this->google->syncScheduleToShopCalendarsOnUpdate($schedule), $slot, $schedule);
        }
    }

    public function needsSchedule(PhotoSlot $slot): bool
    {
        return $slot->customer_id !== null
            && ! $slot->details_undecided
            && $slot->shoot_date !== null
            && $slot->shoot_time !== null;
    }

    private function safely(callable $fn, PhotoSlot $slot, StaffSchedule $schedule): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            Log::error('[PhotoSlotSchedule] Google同期失敗（処理は継続）', [
                'slot_id' => $slot->id,
                'schedule_id' => $schedule->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
