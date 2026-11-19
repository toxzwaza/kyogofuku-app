<?php

namespace Tests\Feature\SeijinPrep;

use App\Models\Customer;
use App\Models\SeijinPrepSlot;
use App\Models\SeijinPrepVenue;
use App\Models\Shop;
use App\Models\User;
use App\Services\GoogleCalendarSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * 成人式お支度の枠：定員を超えない・場所だけの登録・移動・取り消し・見える範囲・まとめて作成
 */
class SeijinPrepTest extends TestCase
{
    use RefreshDatabase;

    private User $okayamaUser;
    private Shop $okayamaShop;
    private Shop $fukuiShop;
    private SeijinPrepVenue $plaza;
    private SeijinPrepVenue $salon;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-19 09:00:00');
        $this->spy(GoogleCalendarSyncService::class);

        $this->okayamaShop = Shop::create(['name' => '岡山店', 'group_key' => 'okayama', 'is_active' => true]);
        $this->fukuiShop = Shop::create(['name' => '福井店', 'group_key' => 'fukui', 'is_active' => true]);
        $this->okayamaUser = User::factory()->create();
        $this->okayamaUser->shops()->attach($this->okayamaShop->id);

        $this->plaza = SeijinPrepVenue::create(['name' => '岡山プラザホテル', 'kind' => 'own', 'shop_group' => 'okayama', 'slot_interval_minutes' => 20]);
        $this->salon = SeijinPrepVenue::create(['name' => '福井の美容室', 'kind' => 'salon', 'shop_group' => 'fukui']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seats(SeijinPrepVenue $venue, string $date, string $time, int $count): void
    {
        for ($i = 1; $i <= $count; $i++) {
            SeijinPrepSlot::create(['seijin_prep_venue_id' => $venue->id, 'prep_date' => $date, 'prep_time' => $time.':00', 'seat_no' => $i]);
        }
    }

    private function customer(string $name = '山田 花子'): Customer
    {
        return Customer::create(['name' => $name]);
    }

    private function saveFor(Customer $c, array $data)
    {
        return $this->actingAs($this->okayamaUser)->putJson(route('admin.customers.seijin-prep.save', $c), $data);
    }

    public function test_定員を超えて枠に入れられない(): void
    {
        $this->seats($this->plaza, '2027-01-10', '08:00', 2);
        $a = $this->customer('A');
        $b = $this->customer('B');
        $c = $this->customer('C');
        $body = ['mode' => 'venue', 'venue_id' => $this->plaza->id, 'prep_date' => '2027-01-10', 'prep_time' => '08:00'];

        $this->saveFor($a, $body)->assertOk();
        $this->saveFor($b, $body)->assertOk();
        $this->saveFor($c, $body)->assertStatus(422)->assertJsonValidationErrors('prep_time');

        $this->assertSame(2, SeijinPrepSlot::whereNotNull('customer_id')->count());
        $this->assertSame([1, 2], SeijinPrepSlot::whereIn('customer_id', [$a->id, $b->id])->orderBy('seat_no')->pluck('seat_no')->all());
    }

    public function test_場所だけ登録してから枠に移すと_席が埋まり場所だけの行は消える(): void
    {
        $this->seats($this->plaza, '2027-01-10', '08:00', 1);
        $c = $this->customer();

        $this->saveFor($c, ['mode' => 'venue', 'venue_id' => $this->plaza->id, 'preferred_time' => '朝早め', 'remarks' => 'メモ'])->assertOk();
        $this->assertSame(1, SeijinPrepSlot::query()->unscheduled()->count());

        $this->saveFor($c, ['mode' => 'venue', 'venue_id' => $this->plaza->id, 'prep_date' => '2027-01-10', 'prep_time' => '08:00', 'preferred_time' => '朝早め'])->assertOk();

        $this->assertSame(0, SeijinPrepSlot::query()->unscheduled()->count());
        $this->assertSame(1, SeijinPrepSlot::count()); // 席1つだけ
        $this->assertSame($c->id, SeijinPrepSlot::first()->customer_id);
    }

    public function test_別の時刻へ移すと元の席は空きに戻る_取り消しでも席は残る(): void
    {
        $this->seats($this->plaza, '2027-01-10', '08:00', 1);
        $this->seats($this->plaza, '2027-01-10', '08:20', 1);
        $c = $this->customer();
        $this->saveFor($c, ['mode' => 'venue', 'venue_id' => $this->plaza->id, 'prep_date' => '2027-01-10', 'prep_time' => '08:00'])->assertOk();
        $this->saveFor($c, ['mode' => 'venue', 'venue_id' => $this->plaza->id, 'prep_date' => '2027-01-10', 'prep_time' => '08:20', 'remarks' => '移した'])->assertOk();

        $this->assertNull(SeijinPrepSlot::where('prep_time', '08:00:00')->first()->customer_id);
        $this->assertSame($c->id, SeijinPrepSlot::where('prep_time', '08:20:00')->first()->customer_id);

        $this->actingAs($this->okayamaUser)->deleteJson(route('admin.customers.seijin-prep.destroy', $c))->assertOk();
        $this->assertSame(2, SeijinPrepSlot::count());
        $this->assertSame(0, SeijinPrepSlot::whereNotNull('customer_id')->count());
        $this->assertNull(SeijinPrepSlot::where('prep_time', '08:20:00')->first()->remarks);
    }

    public function test_他店お支度と未定は場所なしで登録でき_取り消すと行が消える(): void
    {
        $c = $this->customer();
        $this->saveFor($c, ['mode' => 'other', 'remarks' => '着物は自宅へ', 'shop_id' => $this->okayamaShop->id])->assertOk();
        $row = SeijinPrepSlot::where('customer_id', $c->id)->first();
        $this->assertTrue((bool) $row->is_other_store);
        $this->assertNull($row->seijin_prep_venue_id);

        $this->saveFor($c, ['mode' => 'undecided'])->assertOk();
        $this->assertFalse((bool) $row->fresh()->is_other_store);
        $this->assertSame(1, SeijinPrepSlot::count());

        $this->actingAs($this->okayamaUser)->deleteJson(route('admin.customers.seijin-prep.destroy', $c))->assertOk();
        $this->assertSame(0, SeijinPrepSlot::count());
    }

    public function test_ほかの店舗グループの場所は選べず_その登録も変えられない(): void
    {
        $c = $this->customer();
        $this->saveFor($c, ['mode' => 'venue', 'venue_id' => $this->salon->id])->assertStatus(422)->assertJsonValidationErrors('venue_id');

        SeijinPrepSlot::create(['seijin_prep_venue_id' => $this->salon->id, 'customer_id' => $c->id, 'seat_no' => 1]);
        $this->saveFor($c, ['mode' => 'undecided'])->assertStatus(422);
        $this->actingAs($this->okayamaUser)->deleteJson(route('admin.customers.seijin-prep.destroy', $c))->assertStatus(422);
        $this->actingAs($this->okayamaUser)->getJson(route('admin.customers.seijin-prep.show', $c))
            ->assertOk()->assertJson(['prep' => null, 'hidden' => true]);
        $this->assertSame($this->salon->id, SeijinPrepSlot::first()->seijin_prep_venue_id);
    }

    public function test_刻みに合わない時刻は登録できない(): void
    {
        $this->actingAs($this->okayamaUser)->postJson(route('admin.seijin-prep-slots.seats.store'), [
            'seijin_prep_venue_id' => $this->plaza->id, 'prep_date' => '2027-01-10', 'prep_time' => '08:30',
        ])->assertStatus(422)->assertJsonValidationErrors('prep_time'); // 20分刻み

        $this->actingAs($this->okayamaUser)->postJson(route('admin.seijin-prep-slots.seats.store'), [
            'seijin_prep_venue_id' => $this->plaza->id, 'prep_date' => '2027-01-10', 'prep_time' => '08:40',
        ])->assertOk();
    }

    public function test_まとめて作成は刻みで区切り_もうある時刻は飛ばす(): void
    {
        $this->seats($this->plaza, '2027-01-10', '08:20', 1);

        $this->actingAs($this->okayamaUser)->postJson(route('admin.seijin-prep-slots.bulk'), [
            'seijin_prep_venue_id' => $this->plaza->id, 'prep_date' => '2027-01-10',
            'start_time' => '08:00', 'end_time' => '09:00', 'capacity' => 3,
        ])->assertOk();

        // 8:00・8:40・9:00 は3席、8:20 は元の1席のまま
        $counts = SeijinPrepSlot::query()->get()->groupBy(fn ($s) => substr($s->prep_time, 0, 5))->map->count()->all();
        ksort($counts);
        $this->assertSame(['08:00' => 3, '08:20' => 1, '08:40' => 3, '09:00' => 3], $counts);
    }

    public function test_定員は登録の人数より減らせず_登録のある席は消せない(): void
    {
        $this->seats($this->plaza, '2027-01-10', '08:00', 3);
        $c = $this->customer();
        $this->saveFor($c, ['mode' => 'venue', 'venue_id' => $this->plaza->id, 'prep_date' => '2027-01-10', 'prep_time' => '08:00'])->assertOk();
        $target = ['seijin_prep_venue_id' => $this->plaza->id, 'prep_date' => '2027-01-10', 'prep_time' => '08:00'];

        $this->actingAs($this->okayamaUser)->putJson(route('admin.seijin-prep-slots.capacity'), $target + ['capacity' => 1])->assertOk();
        $this->assertSame(1, SeijinPrepSlot::count());
        $this->assertSame($c->id, SeijinPrepSlot::first()->customer_id);

        $this->actingAs($this->okayamaUser)->deleteJson(route('admin.seijin-prep-slots.destroy', SeijinPrepSlot::first()))->assertStatus(422);
    }

    public function test_一覧と月の表は自分の店舗グループの場所だけ(): void
    {
        $this->seats($this->plaza, '2027-01-10', '08:00', 2);
        $this->seats($this->salon, '2027-01-10', '09:00', 1);
        $c = $this->customer();
        $this->saveFor($c, ['mode' => 'venue', 'venue_id' => $this->plaza->id])->assertOk();

        $this->actingAs($this->okayamaUser)->get(route('admin.seijin-prep-slots.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/SeijinPrepSlot/Index')
                ->has('venues', 1)
                ->where('venues.0.capacity', 2)
                ->where('venues.0.unscheduled', 1)
                ->has('unscheduled', 1));

        $json = $this->actingAs($this->okayamaUser)->getJson(route('admin.seijin-prep-slots.grid', [
            'studio_ids' => [$this->plaza->id, $this->salon->id], 'month' => '2027-01',
        ]))->assertOk()->json();
        $this->assertCount(1, $json['columns']);
        $this->assertSame(2, $json['cells']['2027-01-10|'.$this->plaza->id]['08:00']['capacity']);
    }

    public function test_枠のある場所は消せない(): void
    {
        $this->seats($this->plaza, '2027-01-10', '08:00', 1);
        $this->actingAs($this->okayamaUser)->delete(route('admin.seijin-prep-venues.destroy', $this->plaza))->assertSessionHas('error');
        $this->assertDatabaseHas('seijin_prep_venues', ['id' => $this->plaza->id]);
    }

    public function test_担当店舗はほかの店舗グループの店にできない(): void
    {
        $c = $this->customer();
        $this->saveFor($c, ['mode' => 'other', 'shop_id' => $this->fukuiShop->id])
            ->assertStatus(422)->assertJsonValidationErrors('shop_id');
        $this->saveFor($c, ['mode' => 'venue', 'venue_id' => $this->plaza->id, 'shop_id' => $this->fukuiShop->id])
            ->assertStatus(422)->assertJsonValidationErrors('shop_id');
        $this->assertSame(0, SeijinPrepSlot::count());

        $this->saveFor($c, ['mode' => 'other', 'shop_id' => $this->okayamaShop->id])->assertOk();
        $this->assertSame($this->okayamaShop->id, SeijinPrepSlot::first()->shop_id);
    }

    public function test_過去の日は今の席のままなら保存でき_ほかの場所の同じ時刻へは移せない(): void
    {
        $garden = SeijinPrepVenue::create(['name' => '岡山ガーデン', 'kind' => 'own', 'shop_group' => 'okayama', 'slot_interval_minutes' => 20]);
        $this->seats($this->plaza, '2026-11-10', '08:00', 1);
        $this->seats($garden, '2026-11-10', '08:00', 1);
        $c = $this->customer();
        SeijinPrepSlot::where('seijin_prep_venue_id', $this->plaza->id)->update(['customer_id' => $c->id]);

        $this->saveFor($c, ['mode' => 'venue', 'venue_id' => $garden->id, 'prep_date' => '2026-11-10', 'prep_time' => '08:00'])
            ->assertStatus(422)->assertJsonValidationErrors('prep_date');
        $this->saveFor($c, ['mode' => 'venue', 'venue_id' => $this->plaza->id, 'prep_date' => '2026-11-10', 'prep_time' => '08:00', 'remarks' => '変更'])
            ->assertOk();

        $this->assertSame($this->plaza->id, SeijinPrepSlot::where('customer_id', $c->id)->first()->seijin_prep_venue_id);
        $this->assertSame('変更', SeijinPrepSlot::where('customer_id', $c->id)->first()->remarks);
    }
}
