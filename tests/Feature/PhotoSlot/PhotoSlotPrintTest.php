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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 前撮り枠の印刷：期間（月をまたいでよい）とスタジオを選び、1日ずつ「行＝時刻・列＝スタジオ」の表にする
 */
class PhotoSlotPrintTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private Shop $shop;
    private PhotoStudio $garden;
    private PhotoStudio $ikashi;
    private PhotoStudio $fukui;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-19 09:00:00');
        $this->spy(GoogleCalendarSyncService::class);

        $this->shop = Shop::create(['name' => '岡山店', 'group_key' => 'okayama', 'is_active' => true]);
        $this->user = User::factory()->create();
        $this->user->shops()->attach($this->shop->id);

        $this->garden = PhotoStudio::create(['name' => '岡山ガーデン', 'shop_group' => 'okayama', 'is_active' => true]);
        $this->ikashi = PhotoStudio::create(['name' => 'いかしの舎', 'shop_group' => 'okayama', 'is_active' => true]);
        $this->fukui = PhotoStudio::create(['name' => '詩仙堂', 'shop_group' => 'fukui', 'is_active' => true]);

        // 土＝月末・日＝月初
        $booked = $this->seat($this->garden, '2027-07-31', '09:00');
        $booked->update(['customer_id' => Customer::create(['name' => '山田 花子'])->id]);
        $this->seat($this->garden, '2027-07-31', '09:00', 2);
        $this->seat($this->garden, '2027-07-31', '08:00');
        $this->seat($this->ikashi, '2027-08-01', '10:00');
        $this->seat($this->garden, '2027-08-15', '10:00');  // 期間の外
        $this->seat($this->fukui, '2027-07-31', '09:00');   // ほかの店舗グループ
        PhotoSlot::create(['details_undecided' => true, 'customer_id' => Customer::create(['name' => '未定 太郎'])->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seat(PhotoStudio $studio, string $date, string $time, int $seatNo = 1): PhotoSlot
    {
        $slot = PhotoSlot::create([
            'photo_studio_id' => $studio->id, 'shoot_date' => $date, 'shoot_time' => $time.':00', 'seat_no' => $seatNo,
        ]);
        $slot->shops()->attach($this->shop->id);

        return $slot;
    }

    private function print(array $params)
    {
        return $this->actingAs($this->user)->get(route('admin.photo-slots.print', $params));
    }

    public function test_月をまたぐ期間を1日ずつの表にする(): void
    {
        $this->print(['from' => '2027-07-31', 'to' => '2027-08-01', 'studio_ids' => [$this->garden->id, $this->ikashi->id]])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/PhotoSlot/Print')
                ->where('from', '2027-07-31')
                ->where('to', '2027-08-01')
                ->has('days', 2)
                ->where('days.0.date', '2027-07-31')
                ->where('days.0.weekday', 6)
                ->where('days.0.times', ['08:00', '09:00'])
                ->where('days.0.booked', 1)
                ->where('days.0.capacity', 3)
                ->has('days.0.columns', 1)
                ->where('days.0.columns.0.studio.name', '岡山ガーデン')
                ->where("days.0.cells.2027-07-31|{$this->garden->id}.09:00.seats.0.customer.name", '山田 花子')
                ->where("days.0.cells.2027-07-31|{$this->garden->id}.09:00.seats.1.customer", null)
                ->where('days.1.date', '2027-08-01')
                ->where('days.1.weekday', 0)
                ->where('days.1.times', ['10:00'])
                ->where('days.1.columns.0.studio.name', 'いかしの舎')
            );
    }

    public function test_日の中の列は枠のあるスタジオだけで名前順(): void
    {
        $this->seat($this->ikashi, '2027-07-31', '11:00');

        $this->print(['from' => '2027-07-31', 'to' => '2027-07-31', 'studio_ids' => [$this->garden->id, $this->ikashi->id]])
            ->assertInertia(fn (Assert $page) => $page
                ->has('days', 1)
                ->where('days.0.columns.0.studio.name', 'いかしの舎')
                ->where('days.0.columns.1.studio.name', '岡山ガーデン')
                ->where('days.0.times', ['08:00', '09:00', '11:00'])
            );
    }

    public function test_ほかの店舗グループのスタジオは選んでも出さない(): void
    {
        $this->print(['from' => '2027-07-31', 'to' => '2027-07-31', 'studio_ids' => [$this->fukui->id]])
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectedStudioIds', [])
                ->has('days', 0)
                ->has('studios', 2) // 選べるのも自分の店舗グループのスタジオだけ
            );
    }

    public function test_スタジオを指定しないときは動いているスタジオすべて(): void
    {
        $this->ikashi->update(['is_active' => false]);

        $this->print(['from' => '2027-07-31', 'to' => '2027-08-01'])
            ->assertInertia(fn (Assert $page) => $page
                ->where('selectedStudioIds', [$this->garden->id])
                ->has('days', 1)
            );
    }

    public function test_期間を指定しないときは今日から月末まで(): void
    {
        $this->print([])
            ->assertInertia(fn (Assert $page) => $page
                ->where('from', '2026-11-19')
                ->where('to', '2026-11-30')
            );
    }

    public function test_終了日が開始日より前ならエラー(): void
    {
        $this->print(['from' => '2027-08-01', 'to' => '2027-07-31'])
            ->assertSessionHasErrors('to');
    }

    public function test_長すぎる期間はエラー(): void
    {
        $this->print(['from' => '2027-01-01', 'to' => '2028-01-02'])  // 367日
            ->assertSessionHasErrors('to');

        $this->print(['from' => '2027-01-01', 'to' => '2028-01-01'])  // 366日
            ->assertOk();
    }

    public function test_1ページの日数は指定しないとき1日(): void
    {
        $this->print(['from' => '2027-07-31', 'to' => '2027-08-01'])
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('perPage', 1)
                ->where('perPageOptions', [1, 2, 3, 4, 6])
            );
    }

    public function test_1ページの日数を指定できる(): void
    {
        foreach ([1, 2, 3, 4, 6] as $n) {
            $this->print(['from' => '2027-07-31', 'to' => '2027-08-01', 'per_page' => $n])
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('perPage', $n)
                    ->has('days', 2) // 日数を変えても載る日は同じ（まとめ方は画面で決める）
                );
        }
    }

    public function test_選べない日数はエラー(): void
    {
        foreach ([0, 5, 7, 'abc'] as $n) {
            $this->print(['from' => '2027-07-31', 'to' => '2027-08-01', 'per_page' => $n])
                ->assertSessionHasErrors('per_page');
        }
    }
}
