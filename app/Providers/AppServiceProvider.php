<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\EventReservation;
use App\Models\StaffSchedule;
use App\Observers\CustomerObserver;
use App\Observers\EventReservationObserver;
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
    }
}
