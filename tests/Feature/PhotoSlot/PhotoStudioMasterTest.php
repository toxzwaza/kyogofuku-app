<?php

namespace Tests\Feature\PhotoSlot;

use App\Models\PhotoSlot;
use App\Models\PhotoStudio;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PhotoStudioMasterTest extends TestCase
{
    use RefreshDatabase;

    private User $okayamaUser;

    protected function setUp(): void
    {
        parent::setUp();
        $shop = Shop::create(['name' => '岡山店', 'group_key' => 'okayama', 'is_active' => true]);
        $this->okayamaUser = User::factory()->create();
        $this->okayamaUser->shops()->attach($shop->id);
    }

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => '岡山スタジオ',
            'address' => '',
            'remarks' => '',
            'shop_group' => 'okayama',
            'is_active' => true,
            'default_start_time' => '09:00',
            'default_end_time' => '17:00',
            'default_capacity' => 2,
            'weekday_hours' => [
                ['weekday' => 6, 'start_time' => '10:00', 'end_time' => '15:00'],
            ],
        ], $overrides);
    }

    public function test_スタジオと曜日の営業時間を登録できる(): void
    {
        $this->actingAs($this->okayamaUser)
            ->post(route('admin.photo-studios.store'), $this->payload())
            ->assertRedirect();

        $studio = PhotoStudio::with('weekdayHours')->firstOrFail();
        $this->assertSame('okayama', $studio->shop_group);
        $this->assertSame(2, $studio->default_capacity);
        $this->assertCount(1, $studio->weekdayHours);
        $this->assertSame(6, (int) $studio->weekdayHours[0]->weekday);
    }

    public function test_30分刻みでない時刻と逆転した時刻は登録できない(): void
    {
        $this->actingAs($this->okayamaUser)
            ->post(route('admin.photo-studios.store'), $this->payload(['default_start_time' => '09:15']))
            ->assertSessionHasErrors('default_start_time');

        $this->actingAs($this->okayamaUser)
            ->post(route('admin.photo-studios.store'), $this->payload(['default_start_time' => '17:00', 'default_end_time' => '09:00']))
            ->assertSessionHasErrors('default_end_time');

        $this->actingAs($this->okayamaUser)
            ->post(route('admin.photo-studios.store'), $this->payload(['default_capacity' => 11]))
            ->assertSessionHasErrors('default_capacity');

        $this->assertSame(0, PhotoStudio::count());
    }

    public function test_編集で曜日の営業時間を入れ替えられる(): void
    {
        $this->actingAs($this->okayamaUser)->post(route('admin.photo-studios.store'), $this->payload());
        $studio = PhotoStudio::firstOrFail();

        $this->actingAs($this->okayamaUser)
            ->put(route('admin.photo-studios.update', $studio), $this->payload([
                'weekday_hours' => [['weekday' => 0, 'start_time' => '10:00', 'end_time' => '12:00']],
            ]))
            ->assertRedirect();

        $this->assertSame([0], $studio->fresh()->weekdayHours->pluck('weekday')->map(fn ($w) => (int) $w)->all());
    }

    public function test_ほかのグループのスタジオは一覧に出ず編集もできない(): void
    {
        $fukui = PhotoStudio::create(['name' => '福井スタジオ', 'shop_group' => 'fukui']);
        $none = PhotoStudio::create(['name' => '未設定スタジオ', 'shop_group' => null]);

        $this->actingAs($this->okayamaUser)
            ->get(route('admin.photo-studios.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('photoStudios.data', 1)
                ->where('photoStudios.data.0.name', '未設定スタジオ'));

        $this->actingAs($this->okayamaUser)->get(route('admin.photo-studios.edit', $fukui))->assertNotFound();
        $this->actingAs($this->okayamaUser)->get(route('admin.photo-studios.edit', $none))->assertOk();
    }

    public function test_枠があるスタジオは削除できない(): void
    {
        $studio = PhotoStudio::create(['name' => '岡山スタジオ', 'shop_group' => 'okayama']);
        PhotoSlot::create(['photo_studio_id' => $studio->id, 'shoot_date' => '2026-12-01', 'shoot_time' => '10:00:00', 'seat_no' => 1]);

        $this->actingAs($this->okayamaUser)
            ->delete(route('admin.photo-studios.destroy', $studio))
            ->assertRedirect();

        $this->assertNotNull($studio->fresh());
    }
}
