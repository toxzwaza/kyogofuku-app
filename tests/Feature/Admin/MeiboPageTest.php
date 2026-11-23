<?php

namespace Tests\Feature\Admin;

use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * 名簿セクション（名簿一覧・ラベル発行・名簿ダッシュボード）の画面。
 * いまは画面だけでデータはダミー（dataSource=dummy）。店舗管理者以上だけが開ける。
 */
class MeiboPageTest extends TestCase
{
    use RefreshDatabase;

    private const PAGES = [
        'admin.meibo.index' => 'Admin/Meibo/Index',
        'admin.meibo.labels' => 'Admin/Meibo/Labels',
        'admin.meibo.dashboard' => 'Admin/Meibo/Dashboard',
    ];

    private function user(?string $role = 'manager'): User
    {
        $user = User::factory()->create(['attendance_role' => $role]);
        $shop = Shop::create(['name' => 'テスト店', 'is_active' => true]);
        $user->shops()->attach($shop->id);

        return $user;
    }

    public function test_manager_can_open_meibo_pages_with_dummy_source(): void
    {
        $user = $this->user();

        foreach (self::PAGES as $route => $component) {
            $this->actingAs($user)->get(route($route))
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component($component)
                    ->where('dataSource', 'dummy'));
        }
    }

    public function test_non_manager_cannot_open_meibo_pages(): void
    {
        $user = $this->user(null);

        foreach (array_keys(self::PAGES) as $route) {
            $this->actingAs($user)->get(route($route))->assertForbidden();
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        foreach (array_keys(self::PAGES) as $route) {
            $this->get(route($route))->assertRedirect(route('login'));
        }
    }
}
