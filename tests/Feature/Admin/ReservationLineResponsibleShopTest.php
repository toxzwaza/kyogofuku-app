<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\CustomerLineContact;
use App\Models\Event;
use App\Models\EventReservation;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReservationLineResponsibleShopTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_change_responsible_shop_of_reservation_line_contact(): void
    {
        $user = User::factory()->create();
        $shopA = Shop::create(['name' => 'A店', 'is_active' => true]);
        $shopB = Shop::create(['name' => 'B店', 'is_active' => true]);

        $event = Event::create([
            'slug' => 'ev-'.uniqid('', true),
            'title' => 'E',
            'form_type' => 'reservation',
        ]);
        $event->shops()->attach([$shopA->id, $shopB->id]);

        $reservation = EventReservation::create([
            'event_id' => $event->id,
            'name' => 'R',
            'email' => 'r@example.com',
            'phone' => '000',
        ]);

        $contact = CustomerLineContact::create([
            'customer_id' => null,
            'event_reservation_id' => $reservation->id,
            'shop_id' => $shopA->id,
            'line_user_id' => 'Ushop1',
            'label' => '本人',
        ]);

        $response = $this->actingAs($user)->patch(
            '/admin/reservations/'.$reservation->id.'/line/responsible-shop',
            ['shop_id' => $shopB->id]
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseHas('customer_line_contacts', [
            'id' => $contact->id,
            'shop_id' => $shopB->id,
        ]);
    }

    public function test_change_responsible_shop_is_rejected_when_customer_linked(): void
    {
        $user = User::factory()->create();
        $shopA = Shop::create(['name' => 'A店', 'is_active' => true]);
        $shopB = Shop::create(['name' => 'B店', 'is_active' => true]);

        $customer = Customer::create(['name' => 'C', 'shop_id' => $shopA->id]);

        $event = Event::create([
            'slug' => 'ev-'.uniqid('', true),
            'title' => 'E',
            'form_type' => 'reservation',
        ]);
        $event->shops()->attach($shopA->id);

        $reservation = EventReservation::create([
            'event_id' => $event->id,
            'customer_id' => $customer->id,
            'name' => 'R',
            'email' => 'r@example.com',
            'phone' => '000',
        ]);

        $response = $this->actingAs($user)->patch(
            '/admin/reservations/'.$reservation->id.'/line/responsible-shop',
            ['shop_id' => $shopB->id]
        );

        $response->assertSessionHasErrors('line');
    }

    public function test_change_responsible_shop_requires_valid_shop(): void
    {
        $user = User::factory()->create();

        $event = Event::create([
            'slug' => 'ev-'.uniqid('', true),
            'title' => 'E',
            'form_type' => 'reservation',
        ]);

        $reservation = EventReservation::create([
            'event_id' => $event->id,
            'name' => 'R',
            'email' => 'r@example.com',
            'phone' => '000',
        ]);

        $response = $this->actingAs($user)->patch(
            '/admin/reservations/'.$reservation->id.'/line/responsible-shop',
            ['shop_id' => 999999]
        );

        $response->assertSessionHasErrors('shop_id');
    }
}
