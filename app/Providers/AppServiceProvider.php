<?php

namespace App\Providers;

use App\Models\StaffSchedule;
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
    }
}
