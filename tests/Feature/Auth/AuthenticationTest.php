<?php

namespace Tests\Feature\Auth;

use App\Models\Shop;
use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();
        $shop = Shop::create(['name' => 'Test Shop', 'is_active' => true]);
        $user->shops()->attach($shop->id);

        $response = $this->post('/login', [
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'password' => 'password',
            'remember' => false,
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();
        $shop = Shop::create(['name' => 'Test Shop', 'is_active' => true]);
        $user->shops()->attach($shop->id);

        $this->post('/login', [
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'password' => 'wrong-password',
            'remember' => false,
        ]);

        $this->assertGuest();
    }

    public function test_users_can_not_authenticate_when_user_does_not_belong_to_shop(): void
    {
        $user = User::factory()->create();
        $shop = Shop::create(['name' => 'Test Shop', 'is_active' => true]);
        $otherShop = Shop::create(['name' => 'Other Shop', 'is_active' => true]);
        $user->shops()->attach($shop->id);

        $this->post('/login', [
            'shop_id' => $otherShop->id,
            'user_id' => $user->id,
            'password' => 'password',
            'remember' => false,
        ]);

        $this->assertGuest();
    }
}
