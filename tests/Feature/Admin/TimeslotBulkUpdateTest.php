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

/**
 * 予約枠一覧のまとめて変更・別イベントとの重なり表示・編集画面からの戻り先
 */
class TimeslotBulkUpdateTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Shop $shop;
    private Event $event;
    private Venue $venue;
    private EventTimeslot $empty;
    private EventTimeslot $booked;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-27 09:00:00');

        $this->shop = Shop::create(['name' => '岡山店', 'is_active' => true]);
        $this->user = User::factory()->create(['attendance_role' => 'manager']);
        $this->user->shops()->attach($this->shop->id);
        $this->venue = Venue::create(['name' => '京呉服好一 岡山店']);

        $this->event = Event::create(['slug' => 'bu-'.uniqid(), 'title' => '店舗予約', 'form_type' => 'reservation', 'is_public' => true]);
        $this->event->shops()->attach($this->shop->id);
        $this->event->venues()->attach($this->venue->id);

        $this->empty = EventTimeslot::create(['event_id' => $this->event->id, 'venue_id' => $this->venue->id, 'start_at' => '2026-10-28 10:00:00', 'capacity' => 2]);
        $this->booked = EventTimeslot::create(['event_id' => $this->event->id, 'venue_id' => $this->venue->id, 'start_at' => '2026-10-28 11:00:00', 'capacity' => 2]);

        EventReservation::create([
            'event_id' => $this->event->id,
            'venue_id' => $this->venue->id,
            'reservation_datetime' => '2026-10-28 11:00:00',
            'name' => '山田 花子',
            'email' => 'hanako@example.com',
            'phone' => '090-1234-5678',
            'status' => 'pending',
            'privacy_agreed' => true,
            'cancel_flg' => false,
            'visitor_count' => 1,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function bulk(array $payload)
    {
        return $this->actingAs($this->user)
            ->postJson(route('admin.events.timeslots.bulk-update', $this->event), $payload);
    }

    public function test_deactivate_and_activate(): void
    {
        $this->bulk(['ids' => [$this->empty->id, $this->booked->id], 'action' => 'deactivate'])
            ->assertOk()
            ->assertJsonPath('message', '2件の枠の受付を停止しました。')
            ->assertJsonCount(2, 'timeslots');
        $this->assertFalse($this->empty->fresh()->is_active);
        $this->assertFalse($this->booked->fresh()->is_active);

        $this->bulk(['ids' => [$this->empty->id], 'action' => 'activate'])->assertOk();
        $this->assertTrue($this->empty->fresh()->is_active);
        $this->assertFalse($this->booked->fresh()->is_active);
    }

    public function test_capacity_never_goes_below_reservations_or_one(): void
    {
        $this->bulk(['ids' => [$this->empty->id, $this->booked->id], 'action' => 'capacity', 'amount' => -2])
            ->assertOk()
            ->assertJsonPath('message', '0件の枠の定員を変更しました。（2件は変更なし）')
            ->assertJsonCount(2, 'skipped')
            ->assertJsonFragment(['id' => $this->booked->id, 'label' => '10/28 11:00', 'reason' => '予約数（1件）を下回るため変更しません'])
            ->assertJsonFragment(['id' => $this->empty->id, 'reason' => '定員は1以上です']);
        $this->assertSame(2, $this->empty->fresh()->capacity);
        $this->assertSame(2, $this->booked->fresh()->capacity);

        $this->bulk(['ids' => [$this->empty->id, $this->booked->id], 'action' => 'capacity', 'amount' => -1])
            ->assertOk()
            ->assertJsonPath('timeslots.1.remaining_capacity', 0);
        $this->assertSame(1, $this->booked->fresh()->capacity);

        $this->bulk(['ids' => [$this->empty->id], 'action' => 'capacity', 'amount' => 0])->assertStatus(422);
    }

    public function test_ids_of_other_events_are_ignored(): void
    {
        $other = Event::create(['slug' => 'other-'.uniqid(), 'title' => '別イベント', 'form_type' => 'reservation', 'is_public' => true]);
        $otherSlot = EventTimeslot::create(['event_id' => $other->id, 'venue_id' => $this->venue->id, 'start_at' => '2026-10-28 10:00:00', 'capacity' => 2]);

        $this->bulk(['ids' => [$otherSlot->id], 'action' => 'deactivate'])
            ->assertOk()
            ->assertJsonCount(0, 'timeslots');
        $this->assertTrue($otherSlot->fresh()->is_active);
    }

    public function test_index_shows_overlaps_with_other_events_of_same_shop(): void
    {
        $fair = Event::create(['slug' => 'fair-'.uniqid(), 'title' => '振袖展示会', 'form_type' => 'reservation', 'is_public' => true]);
        $fair->shops()->attach($this->shop->id);
        $hall = Venue::create(['name' => '催事会場']);
        EventTimeslot::create(['event_id' => $fair->id, 'venue_id' => $hall->id, 'start_at' => '2026-10-28 13:00:00', 'capacity' => 5]);

        $this->actingAs($this->user)
            ->get(route('admin.events.timeslots.index', $this->event))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where("overlaps.2026-10-28.{$this->venue->id}.0.title", '振袖展示会')
                ->where("overlaps.2026-10-28.{$this->venue->id}.0.venue_names.0", '催事会場'));
    }

    public function test_edit_keeps_list_conditions_for_back_link_and_update_redirect(): void
    {
        $return = 'past=1&s=full,stopped&d=2026-10-28&x=1&v='.urlencode('<script>');

        $this->actingAs($this->user)
            ->get(route('admin.timeslots.edit', $this->empty).'?return='.urlencode($return))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('returnQuery', 'past=1&s=full%2Cstopped&d=2026-10-28')
                ->where('backUrl', route('admin.events.timeslots.index', ['event' => $this->event->id, 'past' => 1, 's' => 'full,stopped', 'd' => '2026-10-28'])));

        $this->actingAs($this->user)
            ->put(route('admin.timeslots.update', $this->empty), [
                'venue_id' => $this->venue->id,
                'start_at' => '2026-10-28 10:00:00',
                'capacity' => 3,
                'is_active' => true,
                'return_query' => 'past=1&s=full,stopped&d=2026-10-28',
            ])
            ->assertRedirect(route('admin.events.timeslots.index', ['event' => $this->event->id, 'past' => 1, 's' => 'full,stopped', 'd' => '2026-10-28']));
    }

    public function test_reservation_list_accepts_unset_venue(): void
    {
        $this->actingAs($this->user)
            ->get(route('admin.events.reservations.index', $this->event).'?venue_id=none&reservation_datetime='.urlencode('2026-10-28 11:00:00'))
            ->assertOk();
    }
}
