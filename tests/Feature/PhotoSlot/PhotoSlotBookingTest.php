<?php

namespace Tests\Feature\PhotoSlot;

use App\Models\Customer;
use App\Models\PhotoSlot;
use App\Models\PhotoStudio;
use App\Models\Shop;
use App\Models\StaffSchedule;
use App\Models\User;
use App\Services\GoogleCalendarSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * 前撮りの予約（入れる・移す・取り消す）と、予定（30分）の作り方
 */
class PhotoSlotBookingTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private User $staff;
    private Shop $shop;
    private Shop $shop2;
    private PhotoStudio $studio;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-19 09:00:00');
        // Google カレンダーへは送らない
        $this->spy(GoogleCalendarSyncService::class);

        $this->shop = Shop::create(['name' => '岡山店', 'group_key' => 'okayama', 'is_active' => true]);
        $this->shop2 = Shop::create(['name' => '倉敷店', 'group_key' => 'okayama', 'is_active' => true]);
        $this->user = User::factory()->create();
        $this->user->shops()->attach($this->shop->id);
        $this->staff = User::factory()->create();
        $this->staff->shops()->attach($this->shop2->id);

        $this->studio = PhotoStudio::create([
            'name' => '岡山スタジオ', 'shop_group' => 'okayama', 'is_active' => true,
            'default_start_time' => '09:00', 'default_end_time' => '12:00', 'default_capacity' => 2,
        ]);
        foreach (['10:00', '11:00'] as $time) {
            foreach ([1, 2] as $seat) {
                PhotoSlot::create([
                    'photo_studio_id' => $this->studio->id, 'shoot_date' => '2026-11-25',
                    'shoot_time' => $time.':00', 'seat_no' => $seat,
                ]);
            }
        }
        $this->customer = Customer::create(['name' => '山田 花子']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function book(Customer $customer, string $time, array $overrides = [])
    {
        return $this->actingAs($this->user)->post(route('admin.customers.photo-slots.store', $customer), array_merge([
            'details_undecided' => false,
            'shop_id' => $this->shop->id,
            'photo_studio_id' => $this->studio->id,
            'shoot_date' => '2026-11-25',
            'shoot_time' => $time,
        ], $overrides));
    }

    private function seat(string $time, int $seat): PhotoSlot
    {
        return PhotoSlot::where('shoot_date', '2026-11-25')->where('shoot_time', $time.':00')->where('seat_no', $seat)->firstOrFail();
    }

    public function test_予約で一番小さい空き席に入り_30分の予定ができる(): void
    {
        $this->book($this->customer, '10:00')->assertSessionHasNoErrors()->assertRedirect();

        $slot = $this->seat('10:00', 1);
        $this->assertSame($this->customer->id, $slot->customer_id);
        $this->assertSame([$this->shop->id], $slot->shops()->pluck('shops.id')->all());

        $schedule = StaffSchedule::where('photo_slot_id', $slot->id)->firstOrFail();
        $this->assertSame('2026-11-25 10:00:00', $schedule->start_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-25 10:30:00', $schedule->end_at->format('Y-m-d H:i:s'));
        $this->assertStringContainsString('山田 花子', $schedule->title);
        $this->assertSame([$this->user->id], $schedule->participantUsers()->pluck('users.id')->all());
        // 空き枠には予定を作らない
        $this->assertSame(1, StaffSchedule::count());
    }

    public function test_満員の時刻には入らない(): void
    {
        $other1 = Customer::create(['name' => '客1']);
        $other2 = Customer::create(['name' => '客2']);
        $this->book($other1, '10:00')->assertSessionHasNoErrors();
        $this->book($other2, '10:00')->assertSessionHasNoErrors();
        $this->assertSame($other2->id, $this->seat('10:00', 2)->customer_id);

        // 最後の席が埋まったあとに入れようとしても入らない（同じ最後の1席の取り合いで、2人目はここで止まる）
        $this->book($this->customer, '10:00')->assertSessionHasErrors('shoot_time');
        $this->assertSame(0, PhotoSlot::where('customer_id', $this->customer->id)->count());
        $this->assertSame(2, StaffSchedule::count());
    }

    public function test_別の時刻へ移すと元の席は空きに戻り_予定も移る(): void
    {
        $this->book($this->customer, '10:00');
        $from = $this->seat('10:00', 1);

        $this->actingAs($this->user)->put(route('admin.customers.photo-slots.update', [$this->customer, $from]), [
            'details_undecided' => false, 'shop_id' => $this->shop->id,
            'photo_studio_id' => $this->studio->id, 'shoot_date' => '2026-11-25', 'shoot_time' => '11:00',
            'remarks' => '振袖持ち込み',
        ])->assertSessionHasNoErrors();

        $from->refresh();
        $this->assertNull($from->customer_id); // 消えずに空きに戻る
        $this->assertSame(0, $from->shops()->count());
        $to = $this->seat('11:00', 1);
        $this->assertSame($this->customer->id, $to->customer_id);
        $this->assertSame('振袖持ち込み', $to->remarks);

        $this->assertNull(StaffSchedule::where('photo_slot_id', $from->id)->first());
        $schedule = StaffSchedule::where('photo_slot_id', $to->id)->firstOrFail();
        $this->assertSame('11:00', $schedule->start_at->format('H:i'));
        $this->assertSame('11:30', $schedule->end_at->format('H:i'));
        $this->assertSame(1, StaffSchedule::count());
    }

    public function test_同じ時刻のまま担当店舗を変えると_席はそのままで予定の参加者が変わる(): void
    {
        $this->book($this->customer, '10:00');
        $slot = $this->seat('10:00', 1);

        $this->actingAs($this->user)->put(route('admin.customers.photo-slots.update', [$this->customer, $slot]), [
            'details_undecided' => false, 'shop_id' => $this->shop2->id,
            'photo_studio_id' => $this->studio->id, 'shoot_date' => '2026-11-25', 'shoot_time' => '10:00',
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->customer->id, $slot->fresh()->customer_id);
        $schedule = StaffSchedule::where('photo_slot_id', $slot->id)->firstOrFail();
        $this->assertSame([$this->staff->id], $schedule->participantUsers()->pluck('users.id')->all());
    }

    public function test_詳細未決定では予定を作らず_日時を決めて移すと元の行は消える(): void
    {
        $this->actingAs($this->user)->post(route('admin.customers.photo-slots.store', $this->customer), [
            'details_undecided' => true, 'shop_id' => $this->shop->id,
        ])->assertSessionHasNoErrors();
        $undecided = PhotoSlot::where('customer_id', $this->customer->id)->firstOrFail();
        $this->assertTrue($undecided->details_undecided);
        $this->assertSame(0, StaffSchedule::count());

        $this->actingAs($this->user)->put(route('admin.customers.photo-slots.update', [$this->customer, $undecided]), [
            'details_undecided' => false, 'shop_id' => $this->shop->id,
            'photo_studio_id' => $this->studio->id, 'shoot_date' => '2026-11-25', 'shoot_time' => '11:00',
        ])->assertSessionHasNoErrors();

        $this->assertNull(PhotoSlot::find($undecided->id));
        $this->assertSame($this->customer->id, $this->seat('11:00', 1)->customer_id);
        $this->assertSame(1, StaffSchedule::count());
    }

    public function test_予約済みを詳細未決定に戻すと_席は空きに戻り予定が消える(): void
    {
        $this->book($this->customer, '10:00');
        $slot = $this->seat('10:00', 1);

        $this->actingAs($this->user)->put(route('admin.customers.photo-slots.update', [$this->customer, $slot]), [
            'details_undecided' => true, 'shop_id' => $this->shop->id,
        ])->assertSessionHasNoErrors();

        $this->assertNull($slot->fresh()->customer_id);
        $this->assertTrue(PhotoSlot::where('customer_id', $this->customer->id)->firstOrFail()->details_undecided);
        $this->assertSame(0, StaffSchedule::count());
    }

    public function test_取り消すと席は残り予定が消える(): void
    {
        $this->book($this->customer, '10:00');
        $slot = $this->seat('10:00', 1);
        $this->assertSame(1, StaffSchedule::count());

        $this->actingAs($this->user)->patch(route('admin.photo-slots.release-customer', $slot))->assertSessionHasNoErrors();

        $slot->refresh();
        $this->assertNull($slot->customer_id);
        $this->assertSame(0, $slot->shops()->count());
        $this->assertSame(0, StaffSchedule::count());
    }

    public function test_ほかのグループの担当店舗では入れられない(): void
    {
        $fukui = Shop::create(['name' => '福井店', 'group_key' => 'fukui', 'is_active' => true]);

        $this->book($this->customer, '10:00', ['shop_id' => $fukui->id])->assertSessionHasErrors('shop_id');
        $this->assertNull($this->seat('10:00', 1)->customer_id);
    }

    public function test_ほかのグループのスタジオは選べない(): void
    {
        $fukuiStudio = PhotoStudio::create(['name' => '福井スタジオ', 'shop_group' => 'fukui', 'is_active' => true, 'default_capacity' => 1]);
        PhotoSlot::create(['photo_studio_id' => $fukuiStudio->id, 'shoot_date' => '2026-11-25', 'shoot_time' => '10:00:00', 'seat_no' => 1]);

        $this->book($this->customer, '10:00', ['photo_studio_id' => $fukuiStudio->id])->assertSessionHasErrors('photo_studio_id');
        $this->actingAs($this->user)->getJson(route('admin.photo-slots.availability', ['studio' => $fukuiStudio->id]))->assertNotFound();
    }

    public function test_旧画面の枠IDの送り方でも_空いている一番小さい席に入る(): void
    {
        $this->book(Customer::create(['name' => '客1']), '10:00');

        $this->actingAs($this->user)->post(route('admin.customers.photo-slots.store', $this->customer), [
            'details_undecided' => false, 'shop_id' => $this->shop->id,
            'photo_slot_id' => $this->seat('10:00', 1)->id, // 埋まっている席の ID でも、同じ時刻の空き席に入る
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->customer->id, $this->seat('10:00', 2)->customer_id);
    }

    public function test_空き状況は時刻ごとの空きと定員を返し_自分の席は空きに数える(): void
    {
        $this->book($this->customer, '10:00');
        $mine = $this->seat('10:00', 1);

        $res = $this->actingAs($this->user)->getJson(route('admin.photo-slots.availability', ['studio' => $this->studio->id]))->assertOk();
        $this->assertSame([
            ['date' => '2026-11-25', 'times' => [
                ['time' => '10:00', 'capacity' => 2, 'free' => 1],
                ['time' => '11:00', 'capacity' => 2, 'free' => 2],
            ]],
        ], $res->json('dates'));

        $res = $this->actingAs($this->user)->getJson(route('admin.photo-slots.availability', ['studio' => $this->studio->id, 'exclude_slot' => $mine->id]));
        $this->assertSame(2, $res->json('dates.0.times.0.free'));
    }

    public function test_一覧から別の時刻の枠を選ぶと_その時刻の空き席へ移る(): void
    {
        $this->book($this->customer, '10:00');
        $from = $this->seat('10:00', 1);

        $this->actingAs($this->user)->put(route('admin.photo-slots.update', $from), [
            'photo_slot_id' => $this->seat('11:00', 2)->id,
            'shop_ids' => [$this->shop->id],
        ])->assertSessionHasNoErrors();

        $this->assertNull($from->fresh()->customer_id);
        $this->assertSame($this->customer->id, $this->seat('11:00', 1)->customer_id);
        $this->assertSame('11:00', StaffSchedule::firstOrFail()->start_at->format('H:i'));
    }
}
