<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\CustomerLineContact;
use App\Models\CustomerLineMessage;
use App\Models\Event;
use App\Models\EventReservation;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LineContactUnlinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_unlink_customer_line_contact_and_messages_cascade(): void
    {
        $user = User::factory()->create();
        $shop = Shop::create([
            'name' => 'S',
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'name' => 'C',
            'shop_id' => $shop->id,
        ]);
        $contact = CustomerLineContact::create([
            'customer_id' => $customer->id,
            'shop_id' => $shop->id,
            'line_user_id' => 'Uunlink1',
            'label' => '本人',
        ]);
        $message = CustomerLineMessage::query()->create([
            'customer_line_contact_id' => $contact->id,
            'direction' => CustomerLineMessage::DIRECTION_INBOUND,
            'message_type' => 'text',
            'text' => 'x',
            'line_message_id' => 'mid-x',
            'payload' => null,
            'sent_by_user_id' => null,
        ]);

        $response = $this->actingAs($user)->delete(
            '/admin/customers/'.$customer->id.'/line/contacts/'.$contact->id
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('customer_line_contacts', ['id' => $contact->id]);
        $this->assertDatabaseMissing('customer_line_messages', ['id' => $message->id]);
    }

    public function test_admin_can_unlink_reservation_line_contact(): void
    {
        $user = User::factory()->create();
        $shop = Shop::create([
            'name' => 'S2',
            'is_active' => true,
        ]);
        $event = Event::create([
            'slug' => 'ev-'.uniqid('', true),
            'title' => 'E',
            'form_type' => 'reservation',
        ]);
        $event->shops()->attach($shop->id);

        $reservation = EventReservation::create([
            'event_id' => $event->id,
            'name' => 'R',
            'email' => 'r@example.com',
            'phone' => '000',
        ]);

        $contact = CustomerLineContact::create([
            'customer_id' => null,
            'event_reservation_id' => $reservation->id,
            'shop_id' => $shop->id,
            'line_user_id' => 'Uunlink2',
            'label' => '本人',
        ]);

        $response = $this->actingAs($user)->delete(
            '/admin/reservations/'.$reservation->id.'/line/contacts/'.$contact->id
        );

        $response->assertRedirect();
        $response->assertSessionHas('success');
        $this->assertDatabaseMissing('customer_line_contacts', ['id' => $contact->id]);
    }
}
