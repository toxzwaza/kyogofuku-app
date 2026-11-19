<?php

namespace Tests\Feature\PhotoSlot;

use App\Models\PhotoSlot;
use App\Models\PhotoStudio;
use App\Models\Shop;
use App\Models\User;
use App\Services\GoogleCalendarSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * 前撮りの表：選んだスタジオ（複数）の1か月。登録済みの枠がある日・時刻だけを出す
 */
class PhotoSlotGridTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private PhotoStudio $garden;
    private PhotoStudio $ikashi;
    private PhotoStudio $fukui;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-19 09:00:00');
        $this->spy(GoogleCalendarSyncService::class);

        $shop = Shop::create(['name' => '岡山店', 'group_key' => 'okayama', 'is_active' => true]);
        $this->user = User::factory()->create();
        $this->user->shops()->attach($shop->id);

        $this->garden = PhotoStudio::create(['name' => '岡山ガーデン', 'shop_group' => 'okayama', 'is_active' => true]);
        $this->ikashi = PhotoStudio::create(['name' => 'いかしの舎', 'shop_group' => 'okayama', 'is_active' => true]);
        $this->fukui = PhotoStudio::create(['name' => '詩仙堂', 'shop_group' => 'fukui', 'is_active' => true]);

        $this->seat($this->garden, '2027-03-12', '08:00', $shop);
        $this->seat($this->garden, '2027-03-12', '08:00', $shop, 2);
        $this->seat($this->garden, '2027-03-13', '14:00', $shop);
        $this->seat($this->ikashi, '2027-03-12', '09:00', $shop);
        $this->seat($this->garden, '2027-04-02', '08:00', $shop);
        $this->seat($this->fukui, '2027-03-12', '10:00', null);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function seat(PhotoStudio $studio, string $date, string $time, ?Shop $shop, int $seatNo = 1): PhotoSlot
    {
        $slot = PhotoSlot::create([
            'photo_studio_id' => $studio->id, 'shoot_date' => $date, 'shoot_time' => $time.':00', 'seat_no' => $seatNo,
        ]);
        if ($shop) {
            $slot->shops()->attach($shop->id);
        }

        return $slot;
    }

    private function grid(array $params)
    {
        return $this->actingAs($this->user)->getJson(route('admin.photo-slots.grid', $params));
    }

    public function test_複数のスタジオの1か月の枠だけを日付とスタジオの列で返す(): void
    {
        $res = $this->grid(['studio_ids' => [$this->garden->id, $this->ikashi->id], 'month' => '2027-03'])->assertOk();

        // 列：3/12 いかしの舎・岡山ガーデン（名前順）、3/13 岡山ガーデン。枠のない日は出さない
        $this->assertSame(
            ['2027-03-12|'.$this->ikashi->id, '2027-03-12|'.$this->garden->id, '2027-03-13|'.$this->garden->id],
            array_column($res->json('columns'), 'key')
        );
        // 行：枠のある時刻だけ（08:00〜14:00 の間を埋めない）
        $this->assertSame(['08:00', '09:00', '14:00'], $res->json('times'));
        $this->assertSame(2, $res->json('cells.2027-03-12|'.$this->garden->id.'.08:00.capacity'));
        $this->assertSame(['岡山店'], $res->json('cells.2027-03-12|'.$this->garden->id.'.08:00.seats.0.shops'));
        // 枠のある月（今月以降）
        $this->assertSame(['2027-03', '2027-04'], array_column($res->json('months'), 'month'));
        $this->assertSame('2027-02', $res->json('prev_month'));
        $this->assertSame('2027-04', $res->json('next_month'));
    }

    public function test_見られないグループのスタジオは含めない(): void
    {
        $res = $this->grid(['studio_ids' => [$this->garden->id, $this->fukui->id], 'month' => '2027-03'])->assertOk();

        $this->assertSame([$this->garden->id], array_column($res->json('studios'), 'id'));
        $this->assertNotContains('2027-03-12|'.$this->fukui->id, array_column($res->json('columns'), 'key'));
    }

    public function test_枠のない月は列も行も空(): void
    {
        $res = $this->grid(['studio_ids' => [$this->garden->id], 'month' => '2027-05'])->assertOk();

        $this->assertSame([], $res->json('columns'));
        $this->assertSame([], $res->json('times'));
    }

    public function test_スタジオを選ばないとエラー(): void
    {
        $this->grid(['month' => '2027-03'])->assertStatus(422)->assertJsonValidationErrors('studio_ids');
    }
}
