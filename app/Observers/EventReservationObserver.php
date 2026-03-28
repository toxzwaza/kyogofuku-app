<?php

namespace App\Observers;

use App\Models\EventReservation;
use App\Services\EventReservationCalendarPresentationService;

class EventReservationObserver
{
    public function __construct(
        protected EventReservationCalendarPresentationService $presentationService
    ) {}

    public function updated(EventReservation $reservation): void
    {
        $this->presentationService->syncStaffScheduleFromReservation($reservation);
    }
}
