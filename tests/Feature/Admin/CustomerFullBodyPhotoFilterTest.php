<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\CustomerPhoto;
use App\Models\PhotoType;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class CustomerFullBodyPhotoFilterTest extends TestCase
{
    use RefreshDatabase;

    private function setUpCustomers(): array
    {
        $shop = Shop::create(['name' => 'A', 'is_active' => true]);
        $fullBody = PhotoType::create(['name' => '全身', 'code' => 'full_body', 'sort_order' => 1, 'is_active' => true]);
        $bustUp = PhotoType::create(['name' => 'バストアップ', 'code' => 'bust_up', 'sort_order' => 2, 'is_active' => true]);

        $withPhoto = Customer::create(['name' => '写真あり 太郎', 'shop_id' => $shop->id]);
        CustomerPhoto::create([
            'customer_id' => $withPhoto->id,
            'photo_type_id' => $fullBody->id,
            'file_path' => 'photos/full.jpg',
            'storage_disk' => 'public',
        ]);

        // 全身以外の写真のみ持つ顧客（「写真なし」として扱われるべき）
        $withOtherPhoto = Customer::create(['name' => '他写真 次郎', 'shop_id' => $shop->id]);
        CustomerPhoto::create([
            'customer_id' => $withOtherPhoto->id,
            'photo_type_id' => $bustUp->id,
            'file_path' => 'photos/bust.jpg',
            'storage_disk' => 'public',
        ]);

        $withoutPhoto = Customer::create(['name' => '写真なし 花子', 'shop_id' => $shop->id]);

        return [$withPhoto, $withOtherPhoto, $withoutPhoto];
    }

    public function test_filter_missing_full_body_photo(): void
    {
        $user = User::factory()->create();
        [, $withOtherPhoto, $withoutPhoto] = $this->setUpCustomers();

        $this->actingAs($user)
            ->get(route('admin.customers.index', [
                'customer_shop_id' => ['all'],
                'full_body_photo_presence' => '写真なし',
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('customers.total', 2)
                ->where('filters.full_body_photo_presence', '写真なし')
            );
    }

    public function test_filter_having_full_body_photo(): void
    {
        $user = User::factory()->create();
        $this->setUpCustomers();

        $this->actingAs($user)
            ->get(route('admin.customers.index', [
                'customer_shop_id' => ['all'],
                'full_body_photo_presence' => '写真あり',
            ]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('customers.total', 1)
            );
    }
}
