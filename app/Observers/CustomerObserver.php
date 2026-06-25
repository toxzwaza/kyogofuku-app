<?php

namespace App\Observers;

use App\Models\Customer;
use App\Models\CustomerStage;
use App\Models\EventReservation;
use App\Models\Referral;
use App\Models\ReferralPoint;
use App\Services\EventReservationCalendarPresentationService;

class CustomerObserver
{
    public function __construct(
        protected EventReservationCalendarPresentationService $presentationService
    ) {}

    public function created(Customer $customer): void
    {
        // 紹介機能の基盤を初期化（常に存在させる）
        CustomerStage::firstOrCreate(
            ['customer_id' => $customer->id],
            ['stage' => CustomerStage::STAGE_BRONZE, 'matured_referrals_count' => 0],
        );
        ReferralPoint::firstOrCreate(
            ['customer_id' => $customer->id],
            ['balance' => 0],
        );

        // 被紹介者としての紹介を同期（補完・最初の紹介者のみ有効・contracted昇格）。
        app(\App\Services\Referral\ReferralCustomerSyncService::class)->sync($customer);
    }

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
