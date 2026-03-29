<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerLineContact;
use App\Models\CustomerLineMessage;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardLineUnreadTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_includes_unread_line_inbound_for_user_shops(): void
    {
        $user = User::factory()->create();
        $shop = Shop::create([
            'name' => 'S',
            'is_active' => true,
        ]);
        $user->shops()->attach($shop->id);

        $customer = Customer::create([
            'name' => '山田',
            'shop_id' => $shop->id,
        ]);
        $contact = CustomerLineContact::create([
            'customer_id' => $customer->id,
            'shop_id' => $shop->id,
            'line_user_id' => 'Udash',
            'label' => '本人',
        ]);
        CustomerLineMessage::query()->create([
            'customer_line_contact_id' => $contact->id,
            'direction' => CustomerLineMessage::DIRECTION_INBOUND,
            'message_type' => 'text',
            'text' => '未読テスト',
            'line_message_id' => 'mid-dash-1',
            'payload' => null,
            'sent_by_user_id' => null,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('lineInboundUnreadCount', 1)
                ->has('lineInboundRecentItems', 1)
                ->where('lineInboundRecentItems.0.is_unread', true)
                ->where('lineInboundRecentItems.0.text', '未読テスト'));
    }

    public function test_dashboard_excludes_unread_when_user_not_in_shop(): void
    {
        $user = User::factory()->create();
        $shop = Shop::create([
            'name' => 'S',
            'is_active' => true,
        ]);

        $customer = Customer::create([
            'name' => '山田',
            'shop_id' => $shop->id,
        ]);
        $contact = CustomerLineContact::create([
            'customer_id' => $customer->id,
            'shop_id' => $shop->id,
            'line_user_id' => 'Uother',
            'label' => '本人',
        ]);
        CustomerLineMessage::query()->create([
            'customer_line_contact_id' => $contact->id,
            'direction' => CustomerLineMessage::DIRECTION_INBOUND,
            'message_type' => 'text',
            'text' => '他店舗',
            'line_message_id' => 'mid-dash-2',
            'payload' => null,
            'sent_by_user_id' => null,
        ]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('lineInboundUnreadCount', 0)
                ->has('lineInboundRecentItems', 0));
    }
}
