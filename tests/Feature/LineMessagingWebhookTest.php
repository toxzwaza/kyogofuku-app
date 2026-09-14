<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\CustomerLineContact;
use App\Models\CustomerLineMessage;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
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

    public function test_unknown_user_message_triggers_link_prompt_reply(): void
    {
        Config::set('line.messaging.channel_secret', 'testsecret');
        Config::set('line.messaging.channel_access_token', 'testtoken');
        Config::set('line.liff.welcome_id', '2009633621-TESTLIFF');

        Http::fake(['api.line.me/*' => Http::response(['ok' => true])]);

        $this->postWebhookText('Uunlinked', 'mid-prompt-1', 'こんにちは', 'rtoken-1');

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.line.me/v2/bot/message/reply'
                && $request['replyToken'] === 'rtoken-1'
                && str_contains($request['messages'][0]['text'], 'https://liff.line.me/2009633621-TESTLIFF')
                && str_contains($request['messages'][0]['text'], 'LINE連携');
        });
    }

    public function test_link_prompt_is_not_resent_within_cooldown(): void
    {
        Config::set('line.messaging.channel_secret', 'testsecret');
        Config::set('line.messaging.channel_access_token', 'testtoken');
        Config::set('line.liff.welcome_id', '2009633621-TESTLIFF');

        Http::fake(['api.line.me/*' => Http::response(['ok' => true])]);

        $this->postWebhookText('Ucooldown', 'mid-prompt-2', '1通目', 'rtoken-2');
        $this->postWebhookText('Ucooldown', 'mid-prompt-3', '2通目', 'rtoken-3');

        Http::assertSentCount(1);
    }

    public function test_link_prompt_disabled_by_config(): void
    {
        Config::set('line.messaging.channel_secret', 'testsecret');
        Config::set('line.messaging.channel_access_token', 'testtoken');
        Config::set('line.link_prompt.enabled', false);

        Http::fake();

        $this->postWebhookText('Udisabled', 'mid-prompt-4', 'こんにちは', 'rtoken-4');

        Http::assertNothingSent();
    }

    public function test_linked_user_message_does_not_trigger_link_prompt(): void
    {
        Config::set('line.messaging.channel_secret', 'testsecret');
        Config::set('line.messaging.channel_access_token', 'testtoken');

        $shop = Shop::create([
            'name' => 'S',
            'is_active' => true,
        ]);
        $customer = Customer::create([
            'name' => 'C',
            'shop_id' => $shop->id,
        ]);
        CustomerLineContact::create([
            'customer_id' => $customer->id,
            'shop_id' => $shop->id,
            'line_user_id' => 'Ulinked2',
            'label' => '本人',
        ]);

        Http::fake();

        $this->postWebhookText('Ulinked2', 'mid-prompt-5', 'Linked hello', 'rtoken-5');

        Http::assertNotSent(function ($request) {
            return $request->url() === 'https://api.line.me/v2/bot/message/reply';
        });
    }

    private function postWebhookText(string $lineUserId, string $messageId, string $text, string $replyToken): void
    {
        $payload = [
            'events' => [
                [
                    'type' => 'message',
                    'replyToken' => $replyToken,
                    'source' => ['type' => 'user', 'userId' => $lineUserId],
                    'message' => [
                        'type' => 'text',
                        'id' => $messageId,
                        'text' => $text,
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
    }
}
