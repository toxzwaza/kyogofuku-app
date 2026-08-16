<?php

namespace Tests\Feature\Api;

use App\Models\DeviceRegistration;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DeviceApiTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::create([
            'name' => '岡山店',
            'is_active' => true,
            'device_password' => Hash::make('shop-pass-1234'),
        ]);
    }

    public function test_公開店舗一覧は登録可能な店舗のみ返す(): void
    {
        Shop::create(['name' => '無効店', 'is_active' => false, 'device_password' => Hash::make('x')]);
        Shop::create(['name' => 'パスワード未設定店', 'is_active' => true]);

        $response = $this->getJson('/api/public/shops');

        $response->assertOk()
            ->assertJsonCount(1, 'shops')
            ->assertJsonPath('shops.0.id', $this->shop->id)
            ->assertJsonPath('shops.0.name', '岡山店');
    }

    public function test_API経由で端末登録できる_CSRFトークン不要(): void
    {
        $response = $this->postJson('/api/device/register', [
            'shop_id' => $this->shop->id,
            'password' => 'shop-pass-1234',
            'label' => 'KI-IPAD-01',
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['device_token', 'device_code', 'shop_name']);

        $device = DeviceRegistration::first();
        $this->assertSame('KI-IPAD-01', $device->label);
        $this->assertSame($this->shop->id, $device->shop_id);
    }

    public function test_API経由の端末登録_パスワード不一致は422(): void
    {
        $response = $this->postJson('/api/device/register', [
            'shop_id' => $this->shop->id,
            'password' => 'wrong-pass',
            'label' => 'KI-IPAD-01',
        ]);

        $response->assertStatus(422)->assertJsonPath('success', false);
        $this->assertSame(0, DeviceRegistration::count());
    }

    public function test_API経由でトークン有効性を確認できる(): void
    {
        $register = $this->postJson('/api/device/register', [
            'shop_id' => $this->shop->id,
            'password' => 'shop-pass-1234',
            'label' => 'KI-IPAD-01',
        ])->json();

        $this->postJson('/api/device/status', ['device_token' => $register['device_token']])
            ->assertOk()
            ->assertJsonPath('registered', true)
            ->assertJsonPath('device_code', $register['device_code']);

        $this->postJson('/api/device/status', ['device_token' => 'invalid-token'])
            ->assertOk()
            ->assertJsonPath('registered', false);
    }
}
