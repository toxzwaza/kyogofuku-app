<?php

namespace Database\Seeders;

use App\Models\Venue;
use App\Models\Event;
use Illuminate\Database\Seeder;

class VenueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $venues = [
            [
                'name' => '岡山店',
                'description' => '岡山店の会場です。',
                'address' => '岡山県岡山市',
                'phone' => '086-123-4567',
                'is_active' => true,
            ]
        ];

        foreach ($venues as $venueData) {
            $venue = Venue::create($venueData);
            
            // 予約フォームのイベントに会場を関連付け
            $reservationEvent = Event::where('form_type', 'reservation')->first();
            if ($reservationEvent) {
                $reservationEvent->venues()->attach($venue->id);
            }
        }
    }
}

