<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerResponsibleShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_patch_responsible_shop_updates_customer_shop_id(): void
    {
        $user = User::factory()->create();
        $shopA = Shop::create(['name' => 'A', 'is_active' => true]);
        $shopB = Shop::create(['name' => 'B', 'is_active' => true]);
        $customer = Customer::create([
            'name' => '山田',
            'shop_id' => $shopA->id,
        ]);

        $this->actingAs($user)
            ->patch(route('admin.customers.update-responsible-shop', $customer), [
                'shop_id' => $shopB->id,
            ])
            ->assertRedirect(route('admin.customers.show', $customer));

        $this->assertSame($shopB->id, $customer->fresh()->shop_id);
    }

    public function test_patch_responsible_shop_can_clear_shop(): void
    {
        $user = User::factory()->create();
        $shop = Shop::create(['name' => 'A', 'is_active' => true]);
        $customer = Customer::create([
            'name' => '山田',
            'shop_id' => $shop->id,
        ]);

        $this->actingAs($user)
            ->patch(route('admin.customers.update-responsible-shop', $customer), [
                'shop_id' => null,
            ])
            ->assertRedirect(route('admin.customers.show', $customer));

        $this->assertNull($customer->fresh()->shop_id);
    }
}
