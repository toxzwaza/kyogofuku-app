<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Event;
use App\Models\EventReservation;
use App\Models\PhotoSlot;
use App\Models\PhotoStudio;
use App\Models\StaffSchedule;
use App\Models\User;
use App\Services\GoogleCalendarSyncService;
use Google\Service\Calendar\Event as GoogleEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Google カレンダーに送るイベントの色・件名（袴＝緑＋状態、振袖＝状態で色、前撮り＝予約済みは青）
 * Google へは送らず、送る前のイベントを組み立てるところだけを確かめる
 */
class GoogleCalendarEventColorTest extends TestCase
{
    use RefreshDatabase;

    private function build(StaffSchedule $schedule): GoogleEvent
    {
        $service = new class extends GoogleCalendarSyncService {
            public function build(StaffSchedule $schedule): GoogleEvent
            {
                return $this->buildCalendarEvent($schedule);
            }
        };

        return $service->build($schedule->fresh());
    }

    private function reservationSchedule(string $formType, string $status): StaffSchedule
    {
        $event = Event::create(['slug' => 't-'.uniqid('', true), 'title' => 'E', 'form_type' => $formType]);
        $reservation = EventReservation::create([
            'event_id' => $event->id,
            'name' => '山田 花子',
            'email' => 'r@example.com',
            'phone' => '09000000000',
            'status' => $status,
        ]);

        return StaffSchedule::create([
            'user_id' => User::factory()->create()->id,
            'event_reservation_id' => $reservation->id,
            'title' => '山田 花子/予約',
            'start_at' => '2026-12-01 10:00:00',
            'end_at' => '2026-12-01 11:00:00',
            'sync_to_google_calendar' => false,
        ]);
    }

    public function test_岡山の袴は緑で件名に状態が付く(): void
    {
        $event = $this->build($this->reservationSchedule('reservation_hakama', '確認中'));

        $this->assertSame('10', $event->getColorId());
        $this->assertSame('【確認中】山田 花子/予約', $event->getSummary());
    }

    public function test_福井の袴も緑で件名に状態が付く(): void
    {
        $event = $this->build($this->reservationSchedule('reservation_hakama_fukui', '対応完了済み'));

        $this->assertSame('10', $event->getColorId());
        $this->assertSame('【対応完了済み】山田 花子/予約', $event->getSummary());
    }

    public function test_袴の未対応は緑で件名はそのまま(): void
    {
        $event = $this->build($this->reservationSchedule('reservation_hakama', '未対応'));

        $this->assertSame('10', $event->getColorId());
        $this->assertSame('山田 花子/予約', $event->getSummary());
    }

    public function test_振袖は今までどおり状態で色が変わり件名はそのまま(): void
    {
        $event = $this->build($this->reservationSchedule('reservation', '確認中'));

        $this->assertSame('6', $event->getColorId());
        $this->assertSame('山田 花子/予約', $event->getSummary());
    }

    public function test_前撮りの枠は予約済みが青で空きはグレー(): void
    {
        $studio = PhotoStudio::create([
            'name' => '岡山スタジオ', 'shop_group' => 'okayama', 'is_active' => true,
            'default_start_time' => '09:00', 'default_end_time' => '12:00', 'default_capacity' => 2,
        ]);
        $customer = Customer::create(['name' => '山田 花子']);
        $booked = PhotoSlot::create([
            'photo_studio_id' => $studio->id, 'shoot_date' => '2026-11-25',
            'shoot_time' => '10:00:00', 'seat_no' => 1, 'customer_id' => $customer->id,
        ]);
        $empty = PhotoSlot::create([
            'photo_studio_id' => $studio->id, 'shoot_date' => '2026-11-25',
            'shoot_time' => '10:00:00', 'seat_no' => 2,
        ]);

        $userId = User::factory()->create()->id;
        $makeSchedule = fn (PhotoSlot $slot) => StaffSchedule::create([
            'user_id' => $userId,
            'photo_slot_id' => $slot->id,
            'title' => '前撮り',
            'start_at' => '2026-11-25 10:00:00',
            'end_at' => '2026-11-25 10:30:00',
            'sync_to_google_calendar' => false,
        ]);

        $bookedEvent = $this->build($makeSchedule($booked));
        $this->assertSame('9', $bookedEvent->getColorId());
        $this->assertSame('[前撮り] 山田 花子（岡山スタジオ）', $bookedEvent->getSummary());

        $emptyEvent = $this->build($makeSchedule($empty));
        $this->assertSame('8', $emptyEvent->getColorId());
        $this->assertSame('[前撮り] 未予約（岡山スタジオ）', $emptyEvent->getSummary());
    }
}
