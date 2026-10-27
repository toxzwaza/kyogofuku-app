<?php

namespace Tests\Feature\Admin;

use App\Models\Event;
use App\Models\EventTimeslot;
use App\Models\Shop;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TimeslotBulkTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Shop $shop;
    private Venue $storeA;
    private Venue $storeB;
    private Venue $hall;
    private Event $event;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-27 09:00:00');

        $this->shop = Shop::create(['name' => '岡山店', 'is_active' => true]);
        $this->user = User::factory()->create(['attendance_role' => 'manager']);
        $this->user->shops()->attach($this->shop->id);

        $this->storeA = Venue::create(['name' => '京呉服好一 岡山店']);
        $this->storeB = Venue::create(['name' => '京呉服好一 城東店']);
        $this->hall = Venue::create(['name' => '岡山プラザホテル']);

        $this->event = $this->makeEvent('店舗予約', [$this->storeA, $this->storeB]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function makeEvent(string $title, array $venues): Event
    {
        $event = Event::create([
            'slug' => 'bulk-'.uniqid(),
            'title' => $title,
            'form_type' => 'reservation',
            'is_public' => true,
        ]);
        $event->shops()->attach($this->shop->id);
        $event->venues()->attach(collect($venues)->pluck('id'));

        return $event;
    }

    private function input(array $overrides = []): array
    {
        return array_merge([
            'start_date' => '2026-11-01',
            'end_date' => '2026-11-07',
            'venue_ids' => [$this->storeA->id, $this->storeB->id],
            'exclude_weekdays' => [2, 3],
            'exclude_dates' => [],
            'times' => [['time' => '10:00', 'capacity' => 1], ['time' => '11:00', 'capacity' => 2]],
            'is_active' => true,
        ], $overrides);
    }

    private function cell(array $plan, string $date, int $venueId): array
    {
        return collect($plan['cells'])->first(fn ($c) => $c['date'] === $date && $c['venue_id'] === $venueId);
    }

    public function test_preview_marks_weekdays_excluded_dates_and_existing_slots(): void
    {
        EventTimeslot::create(['event_id' => $this->event->id, 'venue_id' => $this->storeA->id, 'start_at' => '2026-11-05 10:00:00', 'capacity' => 1]);
        EventTimeslot::create(['event_id' => $this->event->id, 'venue_id' => $this->storeA->id, 'start_at' => '2026-11-05 11:00:00', 'capacity' => 1]);
        EventTimeslot::create(['event_id' => $this->event->id, 'venue_id' => $this->storeA->id, 'start_at' => '2026-11-06 10:00:00', 'capacity' => 1]);

        $plan = $this->actingAs($this->user)
            ->postJson(route('admin.events.timeslots.bulk.preview', $this->event), $this->input(['exclude_dates' => ['2026-11-07']]))
            ->assertOk()
            ->json();

        $this->assertCount(14, $plan['cells']); // 7日 × 2会場
        $this->assertSame('create', $this->cell($plan, '2026-11-01', $this->storeA->id)['status']);
        $this->assertSame('weekday', $this->cell($plan, '2026-11-03', $this->storeA->id)['status']); // 火
        $this->assertSame('weekday', $this->cell($plan, '2026-11-04', $this->storeA->id)['status']); // 水
        $this->assertSame('excluded', $this->cell($plan, '2026-11-07', $this->storeA->id)['status']);
        $this->assertSame('existing', $this->cell($plan, '2026-11-05', $this->storeA->id)['status']);

        $partial = $this->cell($plan, '2026-11-06', $this->storeA->id);
        $this->assertSame('create', $partial['status']);
        $this->assertSame(['11:00'], array_column($partial['new_times'], 'time'));
    }

    public function test_preview_detects_overlap_by_venue_where_other_event_has_slots(): void
    {
        // 催事会場で開くFAIR → このイベントの全会場と重なる
        $fair = $this->makeEvent('振袖FAIR', [$this->hall]);
        EventTimeslot::create(['event_id' => $fair->id, 'venue_id' => $this->hall->id, 'start_at' => '2026-11-01 10:00:00', 'capacity' => 1]);

        // 城東店会場だけで開く別イベント → 城東店のみ重なり、岡山店は作成対象
        $meeting = $this->makeEvent('前撮り打合せ会', [$this->storeB]);
        EventTimeslot::create(['event_id' => $meeting->id, 'venue_id' => $this->storeB->id, 'start_at' => '2026-11-02 10:00:00', 'capacity' => 1]);

        // 無効な枠は重なりとみなさない
        EventTimeslot::create(['event_id' => $fair->id, 'venue_id' => $this->hall->id, 'start_at' => '2026-11-05 10:00:00', 'capacity' => 1, 'is_active' => false]);

        $plan = $this->actingAs($this->user)
            ->postJson(route('admin.events.timeslots.bulk.preview', $this->event), $this->input())
            ->json();

        $this->assertSame('conflict', $this->cell($plan, '2026-11-01', $this->storeA->id)['status']);
        $this->assertSame('conflict', $this->cell($plan, '2026-11-01', $this->storeB->id)['status']);
        $this->assertSame('振袖FAIR', $this->cell($plan, '2026-11-01', $this->storeA->id)['conflicts'][0]['title']);

        $this->assertSame('create', $this->cell($plan, '2026-11-02', $this->storeA->id)['status']);
        $this->assertSame('conflict', $this->cell($plan, '2026-11-02', $this->storeB->id)['status']);
        $this->assertSame('同じ会場に枠あり', $this->cell($plan, '2026-11-02', $this->storeB->id)['conflicts'][0]['reason']);

        $this->assertSame('create', $this->cell($plan, '2026-11-05', $this->storeA->id)['status']);
    }

    public function test_overlaps_lists_dates_with_other_event_slots(): void
    {
        $fair = $this->makeEvent('振袖FAIR', [$this->hall]);
        EventTimeslot::create(['event_id' => $fair->id, 'venue_id' => $this->hall->id, 'start_at' => '2026-11-03 10:00:00', 'capacity' => 1]);
        EventTimeslot::create(['event_id' => $fair->id, 'venue_id' => $this->hall->id, 'start_at' => '2026-11-01 10:00:00', 'capacity' => 1]);
        EventTimeslot::create(['event_id' => $fair->id, 'venue_id' => $this->hall->id, 'start_at' => '2026-11-01 13:00:00', 'capacity' => 1]);
        // 無効な枠・期間外・自イベントの枠は出さない
        EventTimeslot::create(['event_id' => $fair->id, 'venue_id' => $this->hall->id, 'start_at' => '2026-11-05 10:00:00', 'capacity' => 1, 'is_active' => false]);
        EventTimeslot::create(['event_id' => $fair->id, 'venue_id' => $this->hall->id, 'start_at' => '2026-12-01 10:00:00', 'capacity' => 1]);
        EventTimeslot::create(['event_id' => $this->event->id, 'venue_id' => $this->storeA->id, 'start_at' => '2026-11-02 10:00:00', 'capacity' => 1]);

        $dates = $this->actingAs($this->user)
            ->getJson(route('admin.events.timeslots.bulk.overlaps', ['event' => $this->event, 'start_date' => '2026-11-01', 'end_date' => '2026-11-07']))
            ->assertOk()
            ->json('dates');

        $this->assertSame(['2026-11-01', '2026-11-03'], array_column($dates, 'date'));
        $this->assertSame('振袖FAIR', $dates[0]['events'][0]['title']);
        $this->assertSame(['岡山プラザホテル'], $dates[0]['events'][0]['venue_names']);
    }

    public function test_store_creates_only_selected_cells_and_skips_existing_times(): void
    {
        EventTimeslot::create(['event_id' => $this->event->id, 'venue_id' => $this->storeA->id, 'start_at' => '2026-11-01 10:00:00', 'capacity' => 1]);

        $this->actingAs($this->user)
            ->post(route('admin.events.timeslots.bulk.store', $this->event), $this->input([
                'cells' => [
                    ['date' => '2026-11-01', 'venue_id' => $this->storeA->id], // 10:00は既存 → 11:00だけ
                    ['date' => '2026-11-02', 'venue_id' => $this->storeB->id],
                    ['date' => '2026-11-03', 'venue_id' => $this->storeB->id], // 除外曜日（火）→ 無視
                    ['date' => '2026-12-01', 'venue_id' => $this->storeA->id], // 期間外 → 無視
                    ['date' => '2026-11-02', 'venue_id' => $this->hall->id],   // イベント外の会場 → 無視
                ],
            ]))
            ->assertRedirect(route('admin.events.timeslots.index', $this->event));

        $slots = EventTimeslot::where('event_id', $this->event->id)->orderBy('start_at')->orderBy('venue_id')->get();
        $this->assertSame([
            '2026-11-01 10:00|'.$this->storeA->id.'|1',
            '2026-11-01 11:00|'.$this->storeA->id.'|2',
            '2026-11-02 10:00|'.$this->storeB->id.'|1',
            '2026-11-02 11:00|'.$this->storeB->id.'|2',
        ], $slots->map(fn ($s) => $s->start_at->format('Y-m-d H:i').'|'.$s->venue_id.'|'.$s->capacity)->all());
    }

    public function test_store_ignores_duplicated_cells(): void
    {
        $this->actingAs($this->user)
            ->post(route('admin.events.timeslots.bulk.store', $this->event), $this->input([
                'cells' => [
                    ['date' => '2026-11-02', 'venue_id' => $this->storeA->id],
                    ['date' => '2026-11-02', 'venue_id' => $this->storeA->id],
                ],
            ]))
            ->assertRedirect(route('admin.events.timeslots.index', $this->event));

        $this->assertSame(2, EventTimeslot::where('event_id', $this->event->id)->count()); // 10:00・11:00 の1回分
    }

    public function test_store_never_creates_past_dates(): void
    {
        $this->actingAs($this->user)
            ->post(route('admin.events.timeslots.bulk.store', $this->event), $this->input([
                'start_date' => '2026-10-20',
                'end_date' => '2026-10-26',
                'cells' => [['date' => '2026-10-25', 'venue_id' => $this->storeA->id]],
            ]))
            ->assertSessionHasErrors('cells');

        $this->assertSame(0, EventTimeslot::count());
    }

    public function test_period_longer_than_limit_is_rejected(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('admin.events.timeslots.bulk.preview', $this->event), $this->input(['end_date' => '2027-03-01']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('end_date');
    }
}
