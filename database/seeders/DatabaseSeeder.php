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
            // イベント予約管理関連
            ShopSeeder::class,
            UserSeeder::class,
            EventSeeder::class,
            EventImageSeeder::class,
            VenueSeeder::class,
            EventTimeslotSeeder::class,
            EventReservationSeeder::class,
            ReservationNoteSeeder::class,
            StaffScheduleSeeder::class,
            // 顧客管理関連
            CeremonyAreaSeeder::class,
            PlanSeeder::class,
            CustomerSeeder::class,
            ContractSeeder::class,
            PhotoTypeSeeder::class,
            CustomerTagSeeder::class,
        ]);
    }
}
