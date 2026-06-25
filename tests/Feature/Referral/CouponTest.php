<?php

namespace Tests\Feature\Referral;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\CustomerCoupon;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CouponTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shop = Shop::create(['name' => '店', 'is_active' => true]);
    }

    private function admin(): User
    {
        $u = User::factory()->create();
        $u->shops()->attach($this->shop->id);

        return $u;
    }

    private function customer(): Customer
    {
        return Customer::create(['name' => '顧客', 'shop_id' => $this->shop->id]);
    }

    public function test_coupon_crud_with_combinable(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.coupons.store'), [
            'name' => '5000円OFF',
            'discount_type' => 'fixed',
            'discount_value' => 5000,
            'valid_days' => 180,
            'combinable' => true,
            'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('coupons', ['name' => '5000円OFF', 'combinable' => true, 'discount_value' => 5000]);

        $coupon = Coupon::first();
        $this->actingAs($admin)->put(route('admin.coupons.update', $coupon), [
            'name' => '5000円OFF（併用不可）',
            'discount_type' => 'fixed',
            'discount_value' => 5000,
            'combinable' => false,
            'status' => 'active',
        ])->assertRedirect();

        $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'name' => '5000円OFF（併用不可）', 'combinable' => false]);
    }

    public function test_distribute_creates_held_with_valid_until(): void
    {
        $coupon = Coupon::create([
            'name' => 'A', 'discount_type' => 'fixed', 'discount_value' => 1000,
            'valid_days' => 30, 'combinable' => false, 'status' => 'active',
        ]);
        $customer = $this->customer();

        $this->actingAs($this->admin())
            ->post(route('admin.customers.coupons.distribute', $customer), ['coupon_id' => $coupon->id])
            ->assertRedirect();

        $cc = CustomerCoupon::where('customer_id', $customer->id)->first();
        $this->assertNotNull($cc);
        $this->assertSame('held', $cc->status);
        $this->assertSame(now()->addDays(30)->toDateString(), $cc->valid_until->toDateString());
    }

    public function test_distribute_rejects_inactive_coupon(): void
    {
        $coupon = Coupon::create([
            'name' => 'B', 'discount_type' => 'fixed', 'discount_value' => 1000,
            'combinable' => false, 'status' => 'inactive',
        ]);
        $customer = $this->customer();

        $this->actingAs($this->admin())
            ->from(route('admin.customers.show', $customer))
            ->post(route('admin.customers.coupons.distribute', $customer), ['coupon_id' => $coupon->id])
            ->assertSessionHasErrors('coupon_id');

        $this->assertSame(0, CustomerCoupon::count());
    }

    public function test_mark_used(): void
    {
        $coupon = Coupon::create([
            'name' => 'C', 'discount_type' => 'fixed', 'discount_value' => 1000,
            'combinable' => false, 'status' => 'active',
        ]);
        $customer = $this->customer();
        $cc = CustomerCoupon::create(['customer_id' => $customer->id, 'coupon_id' => $coupon->id, 'status' => 'held']);

        $this->actingAs($this->admin())
            ->post(route('admin.customer-coupons.use', $cc))
            ->assertRedirect();

        $cc->refresh();
        $this->assertSame('used', $cc->status);
        $this->assertNotNull($cc->used_at);
    }

    public function test_destroy_archives_when_distributed(): void
    {
        $coupon = Coupon::create([
            'name' => 'D', 'discount_type' => 'fixed', 'discount_value' => 1000,
            'combinable' => false, 'status' => 'active',
        ]);
        $customer = $this->customer();
        CustomerCoupon::create(['customer_id' => $customer->id, 'coupon_id' => $coupon->id, 'status' => 'held']);

        $this->actingAs($this->admin())
            ->delete(route('admin.coupons.destroy', $coupon))
            ->assertRedirect();

        $this->assertDatabaseHas('coupons', ['id' => $coupon->id, 'status' => 'archived']);
    }
}
