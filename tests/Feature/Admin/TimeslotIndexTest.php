<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\EventReservation;
use App\Models\EventTimeslot;
use App\Models\Shop;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class TimeslotIndexTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Event $event;
    private Venue $venue;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-27 09:00:00');

        $shop = Shop::create(['name' => '岡山店', 'is_active' => true]);
        $this->user = User::factory()->create(['attendance_role' => 'manager']);
        $this->user->shops()->attach($shop->id);
        $this->venue = Venue::create(['name' => '京呉服好一 岡山店']);

        $this->event = Event::create(['slug' => 'idx-'.uniqid(), 'title' => '店舗予約', 'form_type' => 'reservation', 'is_public' => true]);
        $this->event->shops()->attach($shop->id);
        $this->event->venues()->attach($this->venue->id);

        EventTimeslot::create(['event_id' => $this->event->id, 'venue_id' => $this->venue->id, 'start_at' => '2026-10-20 10:00:00', 'capacity' => 2]);
        EventTimeslot::create(['event_id' => $this->event->id, 'venue_id' => $this->venue->id, 'start_at' => '2026-10-27 10:00:00', 'capacity' => 2]);
        EventTimeslot::create(['event_id' => $this->event->id, 'venue_id' => $this->venue->id, 'start_at' => '2026-10-28 11:00:00', 'capacity' => 1]);

        // 10/28 11:00 は定員1に2件（定員超過）、キャンセル済み・別会場は数えない
        $this->reserve('2026-10-28 11:00:00', $this->venue->id);
        $this->reserve('2026-10-28 11:00:00', $this->venue->id);
        $this->reserve('2026-10-28 11:00:00', $this->venue->id, true);
        $this->reserve('2026-10-28 11:00:00', null);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function reserve(string $datetime, ?int $venueId, bool $canceled = false): void
    {
        EventReservation::create([
            'event_id' => $this->event->id,
            'venue_id' => $venueId,
            'reservation_datetime' => $datetime,
            'name' => '山田 花子',
            'email' => 'hanako@example.com',
            'phone' => '090-1234-5678',
            'status' => 'pending',
            'privacy_agreed' => true,
            'cancel_flg' => $canceled,
            'visitor_count' => 1,
        ]);
    }

    public function test_default_hides_past_dates_and_counts_reservations_per_slot(): void
    {
        $this->actingAs($this->user)
            ->get(route('admin.events.timeslots.index', $this->event))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/Timeslot/Index')
                ->where('includePast', false)
                ->where('pastCount', 1)
                ->has('timeslots', 2)
                ->where('timeslots.0.reserved_count', 0)
                ->where('timeslots.0.remaining_capacity', 2)
                ->where('timeslots.1.reserved_count', 2)
                ->where('timeslots.1.remaining_capacity', 0)
                ->where('timeslots.1.venue.name', '京呉服好一 岡山店'));
    }

    public function test_past_flag_includes_finished_dates(): void
    {
        $this->actingAs($this->user)
            ->get(route('admin.events.timeslots.index', ['event' => $this->event, 'past' => 1]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('includePast', true)
                ->where('pastCount', 0)
                ->has('timeslots', 3));
    }
}
