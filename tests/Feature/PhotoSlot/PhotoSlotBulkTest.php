<?php

namespace Tests\Feature\PhotoSlot;

use App\Models\ActivityLog;
use App\Models\PhotoSlot;
use App\Models\PhotoStudio;
use App\Models\Shop;
use App\Models\StaffSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PhotoSlotBulkTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private PhotoStudio $studio;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-19 09:00:00'); // 木曜

        $shop = Shop::create(['name' => '岡山店', 'group_key' => 'okayama', 'is_active' => true]);
        $this->user = User::factory()->create();
        $this->user->shops()->attach($shop->id);

        $this->studio = PhotoStudio::create([
            'name' => '岡山スタジオ',
            'shop_group' => 'okayama',
            'is_active' => true,
            'default_start_time' => '09:00',
            'default_end_time' => '12:00', // 9:00〜11:30 の6枠
            'default_capacity' => 2,
        ]);
        // 土曜は 10:00〜11:00（10:00・10:30 の2枠）
        $this->studio->weekdayHours()->create(['weekday' => 6, 'start_time' => '10:00', 'end_time' => '11:00']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function cond(array $overrides = []): array
    {
        return array_merge([
            'photo_studio_id' => $this->studio->id,
            'start_date' => '2026-11-18', // 水（過去）〜 11/22 日
            'end_date' => '2026-11-22',
            'exclude_weekdays' => [0],
            'exclude_dates' => [],
        ], $overrides);
    }

    public function test_プレビューは営業時間から日ごとの案を返す(): void
    {
        PhotoSlot::create(['photo_studio_id' => $this->studio->id, 'shoot_date' => '2026-11-20', 'shoot_time' => '09:00:00', 'seat_no' => 1]);

        $days = collect($this->actingAs($this->user)
            ->postJson(route('admin.photo-slots.bulk.preview'), $this->cond())
            ->assertOk()
            ->json('days'))->keyBy('date');

        $this->assertSame('past', $days['2026-11-18']['status']);
        $this->assertSame(['09:00', '09:30', '10:00', '10:30', '11:00', '11:30'], $days['2026-11-19']['times']);
        $this->assertSame(['09:30', '10:00', '10:30', '11:00', '11:30'], $days['2026-11-20']['times']);
        $this->assertSame(['09:00'], $days['2026-11-20']['existing_times']);
        $this->assertSame(['10:00', '10:30'], $days['2026-11-21']['times']);
        $this->assertSame('weekday', $days['2026-11-21']['hours']['source']);
        $this->assertSame('excluded', $days['2026-11-22']['status']);
        $this->assertSame(0, PhotoSlot::where('shoot_date', '2026-11-19')->count());
    }

    public function test_保存で定員の数だけ席を作り_もうある時刻は飛ばす(): void
    {
        PhotoSlot::create(['photo_studio_id' => $this->studio->id, 'shoot_date' => '2026-11-20', 'shoot_time' => '09:00:00', 'seat_no' => 1]);

        $this->actingAs($this->user)
            ->post(route('admin.photo-slots.bulk.store'), $this->cond([
                'days' => [
                    ['date' => '2026-11-19', 'slots' => [['time' => '09:00', 'capacity' => 3], ['time' => '18:00', 'capacity' => 1]]],
                    ['date' => '2026-11-20', 'slots' => [['time' => '09:00', 'capacity' => 2], ['time' => '09:30', 'capacity' => 2]]],
                ],
            ]))
            ->assertRedirect(route('admin.photo-slots.index'));

        $this->assertSame([1, 2, 3], PhotoSlot::where('shoot_date', '2026-11-19')->where('shoot_time', '09:00:00')->orderBy('seat_no')->pluck('seat_no')->all());
        $this->assertSame(1, PhotoSlot::where('shoot_date', '2026-11-19')->where('shoot_time', '18:00:00')->count()); // 営業時間の外も足せる
        $this->assertSame(1, PhotoSlot::where('shoot_date', '2026-11-20')->where('shoot_time', '09:00:00')->count()); // 既存は上書きしない
        $this->assertSame(2, PhotoSlot::where('shoot_date', '2026-11-20')->where('shoot_time', '09:30:00')->count());
        $this->assertSame(0, StaffSchedule::count()); // 空き枠なので予定は作らない
        $this->assertSame(1, ActivityLog::where('route_name', 'admin.photo-slots.bulk.store')->count());
    }

    public function test_期間外_過去_30分刻みでない時刻_定員超えは保存できない(): void
    {
        $cases = [
            ['date' => '2026-11-30', 'slots' => [['time' => '09:00', 'capacity' => 1]]],
            ['date' => '2026-11-18', 'slots' => [['time' => '09:00', 'capacity' => 1]]],
            ['date' => '2026-11-19', 'slots' => [['time' => '09:15', 'capacity' => 1]]],
            ['date' => '2026-11-19', 'slots' => [['time' => '09:00', 'capacity' => 11]]],
        ];
        foreach ($cases as $day) {
            $this->actingAs($this->user)
                ->post(route('admin.photo-slots.bulk.store'), $this->cond(['days' => [$day]]))
                ->assertSessionHasErrors();
        }
        $this->assertSame(0, PhotoSlot::count());
    }

    public function test_ほかのグループのスタジオには作れない(): void
    {
        $fukui = PhotoStudio::create(['name' => '福井', 'shop_group' => 'fukui', 'default_start_time' => '09:00', 'default_end_time' => '12:00']);

        $this->actingAs($this->user)
            ->postJson(route('admin.photo-slots.bulk.preview'), $this->cond(['photo_studio_id' => $fukui->id]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('photo_studio_id');
    }

    public function test_期間は93日まで(): void
    {
        $this->actingAs($this->user)
            ->postJson(route('admin.photo-slots.bulk.preview'), $this->cond(['start_date' => '2026-11-19', 'end_date' => '2027-02-20']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('end_date');
    }

    public function test_作成画面には自分のグループの有効なスタジオだけ出る(): void
    {
        PhotoStudio::create(['name' => '福井', 'shop_group' => 'fukui']);
        PhotoStudio::create(['name' => '無効', 'shop_group' => 'okayama', 'is_active' => false]);

        $this->actingAs($this->user)
            ->get(route('admin.photo-slots.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Admin/PhotoSlot/Create')
                ->has('studios', 1)
                ->where('studios.0.name', '岡山スタジオ'));
    }
}
