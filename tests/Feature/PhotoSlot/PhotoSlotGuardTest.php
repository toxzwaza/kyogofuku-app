<?php

namespace Tests\Feature\PhotoSlot;

use App\Models\Customer;
use App\Models\PhotoSlot;
use App\Models\PhotoStudio;
use App\Models\Shop;
use App\Models\User;
use App\Services\GoogleCalendarSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * 店舗グループの見える範囲と、予約の入った席を守る処理（Codex レビューの指摘への対応）
 */
class PhotoSlotGuardTest extends TestCase
{
    use RefreshDatabase;

    private User $okayamaUser;
    private Shop $okayamaShop;
    private Shop $fukuiShop;
    private PhotoStudio $garden;
    private PhotoStudio $shisendo;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-19 09:00:00');
        $this->spy(GoogleCalendarSyncService::class);

        $this->okayamaShop = Shop::create(['name' => '岡山店', 'group_key' => 'okayama', 'is_active' => true]);
        $this->fukuiShop = Shop::create(['name' => '福井店', 'group_key' => 'fukui', 'is_active' => true]);
        $this->okayamaUser = User::factory()->create();
        $this->okayamaUser->shops()->attach($this->okayamaShop->id);

        $this->garden = PhotoStudio::create(['name' => '岡山ガーデン', 'shop_group' => 'okayama', 'is_active' => true]);
        $this->shisendo = PhotoStudio::create(['name' => '詩仙堂', 'shop_group' => 'fukui', 'is_active' => true]);
        $this->customer = Customer::create(['name' => '山田 花子']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seat(PhotoStudio $studio, string $date, string $time, int $seatNo = 1, ?int $customerId = null): PhotoSlot
    {
        return PhotoSlot::create([
            'photo_studio_id' => $studio->id, 'shoot_date' => $date, 'shoot_time' => $time.':00',
            'seat_no' => $seatNo, 'customer_id' => $customerId,
        ]);
    }

    public function test_福井店だけが担当の日時未定の枠は岡山の人に見えない(): void
    {
        $fukui = PhotoSlot::create(['customer_id' => $this->customer->id, 'details_undecided' => true]);
        $fukui->shops()->attach($this->fukuiShop->id);
        $okayama = PhotoSlot::create(['customer_id' => $this->customer->id, 'details_undecided' => true]);
        $okayama->shops()->attach($this->okayamaShop->id);
        $noShop = PhotoSlot::create(['customer_id' => $this->customer->id, 'details_undecided' => true]);

        $ids = PhotoSlot::query()->visibleToUser($this->okayamaUser)->pluck('id')->all();

        $this->assertNotContains($fukui->id, $ids);
        $this->assertContains($okayama->id, $ids);
        $this->assertContains($noShop->id, $ids); // 担当店舗のない枠はこれまでどおり見える
    }

    public function test_顧客詳細からほかのグループの前撮りは変えられない(): void
    {
        $slot = $this->seat($this->shisendo, '2027-03-12', '10:00', 1, $this->customer->id);

        $this->actingAs($this->okayamaUser)
            ->from(route('admin.customers.show', $this->customer))
            ->put(route('admin.customers.photo-slots.update', [$this->customer, $slot]), [
                'details_undecided' => true, 'shop_id' => $this->okayamaShop->id,
            ])
            ->assertSessionHasErrors('error');

        $slot->refresh();
        $this->assertFalse((bool) $slot->details_undecided);
        $this->assertSame($this->shisendo->id, $slot->photo_studio_id);
    }

    public function test_予約の入った席は消せず_空き席は消せる(): void
    {
        $booked = $this->seat($this->garden, '2027-03-12', '10:00', 1, $this->customer->id);
        $vacant = $this->seat($this->garden, '2027-03-12', '10:00', 2);

        $this->actingAs($this->okayamaUser)->deleteJson(route('admin.photo-slots.destroy', $booked))->assertStatus(422);
        $this->actingAs($this->okayamaUser)->deleteJson(route('admin.photo-slots.destroy', $vacant))->assertOk();

        $this->assertDatabaseHas('photo_slots', ['id' => $booked->id]);
        $this->assertDatabaseMissing('photo_slots', ['id' => $vacant->id]);
    }

    public function test_まとめて移すとき予約の入った席は動かさない(): void
    {
        $booked = $this->seat($this->garden, '2027-03-12', '10:00', 1, $this->customer->id);
        $vacant = $this->seat($this->garden, '2027-03-12', '11:00');

        $this->actingAs($this->okayamaUser)->put(route('admin.photo-slots.bulk-update'), [
            'slot_ids' => [$booked->id, $vacant->id],
            'shoot_date' => '2027-03-20',
            'photo_studio_id' => $this->garden->id,
        ])->assertSessionHas('slotErrors');

        $this->assertSame('2027-03-12', Carbon::parse($booked->fresh()->shoot_date)->toDateString());
        $this->assertSame('2027-03-20', Carbon::parse($vacant->fresh()->shoot_date)->toDateString());
    }
}
