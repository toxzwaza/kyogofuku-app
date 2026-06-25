<?php

namespace App\Providers;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerLineContact;
use App\Models\Event;
use App\Models\EventReservation;
use App\Models\PhotoSlot;
use App\Models\StaffSchedule;
use App\Observers\ContractObserver;
use App\Observers\CustomerLineContactObserver;
use App\Observers\CustomerObserver;
use App\Observers\EventObserver;
use App\Observers\EventReservationObserver;
use App\Observers\PhotoSlotObserver;
use App\Observers\StaffScheduleObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        StaffSchedule::observe(StaffScheduleObserver::class);
        EventReservation::observe(EventReservationObserver::class);
        Customer::observe(CustomerObserver::class);
        CustomerLineContact::observe(CustomerLineContactObserver::class);
        Contract::observe(ContractObserver::class);
        PhotoSlot::observe(PhotoSlotObserver::class);
        Event::observe(EventObserver::class);
    }
}
