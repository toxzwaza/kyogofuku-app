<?php

namespace Tests\Feature\Admin;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerConstraint;
use App\Models\CustomerPhoto;
use App\Models\ConstraintTemplate;
use App\Models\Event;
use App\Models\EventReservation;
use App\Models\PhotoType;
use App\Models\Plan;
use App\Models\Shop;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class OverviewShopScopeTest extends TestCase
{
    use RefreshDatabase;

    private function createEventForShops(array $shopIds, string $slug): Event
    {
        $event = Event::create([
            'slug' => $slug,
            'title' => 'テストイベント '.$slug,
            'form_type' => 'reservation',
            'is_public' => true,
        ]);
        $event->shops()->attach($shopIds);

        return $event;
    }

    private function createReservation(Event $event, array $attributes = []): EventReservation
    {
        return EventReservation::create(array_merge([
            'event_id' => $event->id,
            'name' => '予約者',
            'email' => 'test@example.com',
            'phone' => '090-0000-0000',
            'reservation_datetime' => Carbon::today()->setTime(12, 0)->format('Y-m-d H:i:s'),
        ], $attributes));
    }

    public function test_reservation_stats_are_scoped_to_user_shops(): void
    {
        $user = User::factory()->create();
        $shopA = Shop::create(['name' => 'A', 'is_active' => true]);
        $shopB = Shop::create(['name' => 'B', 'is_active' => true]);
        $user->shops()->attach($shopA->id, ['main' => true]);

        $eventA = $this->createEventForShops([$shopA->id], 'event-a');
        $eventB = $this->createEventForShops([$shopB->id], 'event-b');

        $this->createReservation($eventA);
        $this->createReservation($eventB);

        $this->actingAs($user)
            ->get(route('admin.overview'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.today_count', 1)
            );
    }

    public function test_multi_shop_event_reservation_is_not_double_counted(): void
    {
        $user = User::factory()->create();
        $shopA = Shop::create(['name' => 'A', 'is_active' => true]);
        $shopB = Shop::create(['name' => 'B', 'is_active' => true]);
        $user->shops()->attach($shopA->id, ['main' => true]);
        $user->shops()->attach($shopB->id, ['main' => false]);

        $eventAB = $this->createEventForShops([$shopA->id, $shopB->id], 'event-ab');
        $this->createReservation($eventAB);

        $this->actingAs($user)
            ->get(route('admin.overview'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.today_count', 1)
            );
    }

    public function test_input_alerts_are_scoped_to_user_shops(): void
    {
        $user = User::factory()->create();
        $shopA = Shop::create(['name' => 'A', 'is_active' => true]);
        $shopB = Shop::create(['name' => 'B', 'is_active' => true]);
        $user->shops()->attach($shopA->id, ['main' => true]);

        $fullBody = PhotoType::create(['name' => '全身', 'code' => 'full_body', 'sort_order' => 1, 'is_active' => true]);
        $template = ConstraintTemplate::create(['name' => 'テスト制約', 'body' => 'テスト本文']);
        $plan = Plan::create(['name' => 'テストプラン', 'code' => 'test-plan', 'is_active' => true]);
        $contractBase = fn (Customer $customer, string $status) => [
            'customer_id' => $customer->id,
            'shop_id' => $customer->shop_id,
            'plan_id' => $plan->id,
            'contract_date' => Carbon::today()->format('Y-m-d'),
            'kimono_type' => '振袖',
            'total_amount' => 100000,
            'status' => $status,
        ];

        // 店舗A：全身写真・成約（確定）・制約がすべて登録済みの顧客
        $complete = Customer::create(['name' => '完了 太郎', 'shop_id' => $shopA->id]);
        CustomerPhoto::create([
            'customer_id' => $complete->id,
            'photo_type_id' => $fullBody->id,
            'file_path' => 'photos/full.jpg',
            'storage_disk' => 'public',
        ]);
        Contract::create($contractBase($complete, '確定'));
        CustomerConstraint::create(['customer_id' => $complete->id, 'constraint_template_id' => $template->id]);

        // 店舗A：何も登録されていない顧客（全項目でカウントされる）
        Customer::create(['name' => '未入力 花子', 'shop_id' => $shopA->id]);

        // 店舗A：保留成約を持つ顧客
        $pending = Customer::create(['name' => '保留 次郎', 'shop_id' => $shopA->id]);
        Contract::create($contractBase($pending, '保留'));

        // 店舗B：何も登録されていない顧客（所属外なのでカウントされない）
        Customer::create(['name' => '他店 三郎', 'shop_id' => $shopB->id]);

        $this->actingAs($user)
            ->get(route('admin.overview'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('input_alerts.pending_contracts', 1)
                ->where('input_alerts.undecided_photo_slots', 0)
                // 未入力花子＋保留次郎（写真なし）の2人
                ->where('input_alerts.missing_full_body_photo', 2)
                // 未入力花子のみ（保留次郎は成約自体はある）
                ->where('input_alerts.missing_contract', 1)
                // 未入力花子＋保留次郎の2人
                ->where('input_alerts.missing_constraint', 2)
                ->where('user_shop_ids', [$shopA->id])
            );
    }

    public function test_user_without_shops_sees_zero_counts(): void
    {
        $user = User::factory()->create();
        $shopA = Shop::create(['name' => 'A', 'is_active' => true]);

        $eventA = $this->createEventForShops([$shopA->id], 'event-a');
        $this->createReservation($eventA);
        Customer::create(['name' => '未入力 花子', 'shop_id' => $shopA->id]);

        $this->actingAs($user)
            ->get(route('admin.overview'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('stats.today_count', 0)
                ->where('input_alerts.missing_contract', 0)
                ->where('input_alerts.missing_constraint', 0)
                ->where('input_alerts.missing_full_body_photo', 0)
                ->where('user_shop_ids', [])
            );
    }
}
