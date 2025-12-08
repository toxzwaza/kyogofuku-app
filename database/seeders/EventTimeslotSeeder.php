<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventTimeslot;
use Illuminate\Database\Seeder;
use Carbon\Carbon;

class EventTimeslotSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        // 予約フォームのイベントのみに予約枠を追加
        $reservationEvent = Event::where('form_type', 'reservation')->first();

        if ($reservationEvent) {
            // 今日から1週間分の予約枠を作成（1日3枠）
            for ($day = 0; $day < 7; $day++) {
                $date = Carbon::now()->addDays($day)->startOfDay();

                // 10:00, 13:00, 16:00の3枠
                $times = [10, 13, 16];
                foreach ($times as $hour) {
                    EventTimeslot::create([
                        'event_id' => $reservationEvent->id,
                        'start_at' => $date->copy()->setTime($hour, 0),
                        'capacity' => 3,
                        'is_active' => true,
                    ]);
                }
            }
        }
    }
}

