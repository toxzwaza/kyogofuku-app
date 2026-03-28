<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\EventReservation;
use App\Services\EventReservationCalendarPresentationService;

class CustomerObserver
{
    public function __construct(
        protected EventReservationCalendarPresentationService $presentationService
    ) {}

    public function updated(Customer $customer): void
    {
        if (!$customer->wasChanged('ceremony_area_id')) {
            return;
        }

        EventReservation::query()
            ->where('customer_id', $customer->id)
            ->where('cancel_flg', false)
            ->with(['event', 'schedule', 'customer.ceremonyArea', 'venue'])
            ->each(function (EventReservation $reservation) {
                $this->presentationService->syncStaffScheduleFromReservation($reservation);
            });
    }
}
