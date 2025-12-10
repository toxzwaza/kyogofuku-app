<?php

namespace Database\Seeders;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        $this->call([
            ShopSeeder::class,
            UserSeeder::class,
            EventSeeder::class,
            EventImageSeeder::class,
            VenueSeeder::class,
            EventTimeslotSeeder::class,
            EventReservationSeeder::class,
            ReservationNoteSeeder::class,
            StaffScheduleSeeder::class,
        ]);
    }
}
