<?php

namespace Tests\Feature\Admin;

use App\Models\Customer;
use App\Models\CustomerLineContact;
use App\Models\CustomerLineMessage;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerLineMessageThreadTest extends TestCase
{
    use RefreshDatabase;

    public function test_contact_messages_json_includes_inbound_and_outbound_ordered(): void
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
            'line_user_id' => 'Utest',
            'label' => '本人',
        ]);

        CustomerLineMessage::query()->create([
            'customer_line_contact_id' => $contact->id,
            'direction' => CustomerLineMessage::DIRECTION_INBOUND,
            'message_type' => 'text',
            'text' => 'hello from line',
            'line_message_id' => 'mid-in-1',
            'payload' => null,
            'sent_by_user_id' => null,
        ]);
        CustomerLineMessage::query()->create([
            'customer_line_contact_id' => $contact->id,
            'direction' => CustomerLineMessage::DIRECTION_OUTBOUND,
            'message_type' => 'text',
            'text' => 'reply from shop',
            'line_message_id' => null,
            'payload' => null,
            'sent_by_user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->getJson(
            route('admin.customers.line.contact-messages', [$customer, $contact])
        );

        $response->assertOk();
        $response->assertJsonCount(2, 'messages');
        $messages = $response->json('messages');
        $this->assertSame(CustomerLineMessage::DIRECTION_INBOUND, $messages[0]['direction']);
        $this->assertSame('hello from line', $messages[0]['text']);
        $this->assertSame(CustomerLineMessage::DIRECTION_OUTBOUND, $messages[1]['direction']);
        $this->assertSame('reply from shop', $messages[1]['text']);
        $this->assertSame($user->name, $messages[1]['sent_by']);

        $this->assertNotNull(
            CustomerLineMessage::query()->where('text', 'hello from line')->value('admin_read_at')
        );
    }
}
