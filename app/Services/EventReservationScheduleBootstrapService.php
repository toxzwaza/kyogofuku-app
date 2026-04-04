<?php

namespace App\Services;

use App\Models\EventReservation;
use App\Models\StaffSchedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class EventReservationScheduleBootstrapService
{
    public function __construct(
        protected GoogleCalendarSyncService $googleCalendarSyncService,
        protected EventReservationCalendarPresentationService $presentationService
    ) {}

    /**
     * 予約フォームかつ来店日時がある予約について、スケジュールが無ければ作成し Google カレンダーへ同期する。
     */
    public function bootstrapIfApplicable(EventReservation $reservation): void
    {
        $reservation->loadMissing(['event', 'venue']);

        if (! $reservation->event?->usesTimeslotReservation()) {
            return;
        }

        if ($reservation->cancel_flg) {
            return;
        }

        if (empty($reservation->reservation_datetime)) {
            return;
        }

        if ($reservation->relationLoaded('schedule')) {
            if ($reservation->schedule !== null) {
                return;
            }
        } elseif ($reservation->schedule()->exists()) {
            return;
        }

        $ownerUserId = Auth::id() ?? (int) config('services.google.calendar_reservation_owner_user_id');
        if ($ownerUserId < 1) {
            Log::warning('[EventReservationSchedule] スケジュール自動作成をスキップ（owner user が未設定）', [
                'reservation_id' => $reservation->id,
            ]);

            return;
        }

        $startAt = Carbon::parse($reservation->reservation_datetime);
        $endAt = $startAt->copy()->addHour();

        $title = $this->presentationService->buildTitle($reservation);
        $description = $this->presentationService->buildDescription($reservation);

        $schedule = StaffSchedule::create([
            'user_id' => $ownerUserId,
            'event_reservation_id' => $reservation->id,
            'title' => $title,
            'description' => $description,
            'start_at' => $startAt,
            'end_at' => $endAt,
            'all_day' => false,
            'is_public' => true,
            'sync_to_google_calendar' => true,
        ]);

        $this->googleCalendarSyncService->syncScheduleToShopCalendars($schedule);
    }
}
