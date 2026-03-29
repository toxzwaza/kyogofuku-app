<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerLineContact;
use App\Models\CustomerLineMessage;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class LineMessagingWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_webhook_rejects_invalid_signature(): void
    {
        Config::set('line.messaging.channel_secret', 'mysecret');

        $body = '{"events":[]}';
        $this->call('POST', route('webhook.line.messaging', [], false), [], [], [], [
            'HTTP_X_LINE_SIGNATURE' => 'bad',
            'CONTENT_TYPE' => 'application/json',
        ], $body)
            ->assertStatus(400);
    }

    public function test_text_message_from_unknown_user_goes_to_unknown_queue(): void
    {
        Config::set('line.messaging.channel_secret', 'testsecret');

        Shop::create([
            'name' => 'S',
            'is_active' => true,
        ]);

        $payload = [
            'destination' => 'x',
            'events' => [
                [
                    'type' => 'message',
                    'source' => ['type' => 'user', 'userId' => 'Udeadbeef'],
                    'message' => [
                        'type' => 'text',
                        'id' => 'mid-1',
                        'text' => 'Hello',
                    ],
                ],
            ],
        ];
        $body = json_encode($payload);
        $sig = base64_encode(hash_hmac('sha256', $body, 'testsecret', true));

        $this->call('POST', route('webhook.line.messaging', [], false), [], [], [], [
            'HTTP_X_LINE_SIGNATURE' => $sig,
            'CONTENT_TYPE' => 'application/json',
        ], $body)
            ->assertOk();

        $this->assertDatabaseHas('line_unknown_inbound_messages', [
            'shop_id' => null,
            'line_user_id' => 'Udeadbeef',
            'text' => 'Hello',
        ]);
    }

    public function test_text_message_from_linked_user_stores_in_customer_messages(): void
    {
        Config::set('line.messaging.channel_secret', 'testsecret');

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
            'line_user_id' => 'Ulinked',
            'label' => '本人',
        ]);

        $payload = [
            'events' => [
                [
                    'type' => 'message',
                    'source' => ['type' => 'user', 'userId' => 'Ulinked'],
                    'message' => [
                        'type' => 'text',
                        'id' => 'mid-2',
                        'text' => 'Linked hi',
                    ],
                ],
            ],
        ];
        $body = json_encode($payload);
        $sig = base64_encode(hash_hmac('sha256', $body, 'testsecret', true));

        $this->call('POST', route('webhook.line.messaging', [], false), [], [], [], [
            'HTTP_X_LINE_SIGNATURE' => $sig,
            'CONTENT_TYPE' => 'application/json',
        ], $body)
            ->assertOk();

        $this->assertDatabaseHas('customer_line_messages', [
            'customer_line_contact_id' => $contact->id,
            'direction' => CustomerLineMessage::DIRECTION_INBOUND,
            'text' => 'Linked hi',
        ]);
        $this->assertNull(
            CustomerLineMessage::query()->where('text', 'Linked hi')->value('admin_read_at')
        );
        $this->assertDatabaseMissing('line_unknown_inbound_messages', [
            'line_user_id' => 'Ulinked',
        ]);
    }
}
