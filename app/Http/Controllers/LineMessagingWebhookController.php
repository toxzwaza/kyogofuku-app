<?php

namespace App\Http\Controllers;

use App\Models\CustomerLineContact;
use App\Models\CustomerLineMessage;
use App\Models\LineUnknownInboundMessage;
use App\Services\Line\LineMessagingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LineMessagingWebhookController extends Controller
{
    public function __construct(
        private LineMessagingService $lineMessaging
    ) {}

    public function handle(Request $request)
    {
        $secret = (string) config('line.messaging.channel_secret', '');
        if ($secret === '') {
            Log::warning('LINE webhook: messaging channel secret not configured');

            return response()->json(['message' => 'not configured'], 503);
        }

        $body = $request->getContent();
        $sig = (string) $request->header('X-Line-Signature', '');
        if (! $this->lineMessaging->verifySignature($body, $secret, $sig)) {
            return response()->json(['message' => 'invalid signature'], 400);
        }

        $payload = json_decode($body, true);
        if (! is_array($payload) || empty($payload['events']) || ! is_array($payload['events'])) {
            return response()->json(['message' => 'ok']);
        }

        foreach ($payload['events'] as $event) {
            if (! is_array($event)) {
                continue;
            }
            $this->processEvent($event);
        }

        return response()->json(['message' => 'ok']);
    }

    private function processEvent(array $event): void
    {
        $type = $event['type'] ?? null;
        if ($type !== 'message') {
            return;
        }

        $source = $event['source'] ?? [];
        if (($source['type'] ?? '') !== 'user' || empty($source['userId'])) {
            return;
        }

        $lineUserId = (string) $source['userId'];
        $message = $event['message'] ?? [];
        $messageType = $message['type'] ?? '';

        if ($messageType !== 'text') {
            LineUnknownInboundMessage::query()->create([
                'shop_id' => null,
                'line_user_id' => $lineUserId,
                'text' => null,
                'line_message_id' => $message['id'] ?? null,
                'raw_event' => $event,
                'created_at' => now(),
            ]);

            return;
        }

        $text = isset($message['text']) ? (string) $message['text'] : '';
        $lineMessageId = isset($message['id']) ? (string) $message['id'] : null;

        if ($lineMessageId && CustomerLineMessage::query()->where('line_message_id', $lineMessageId)->exists()) {
            return;
        }
        if ($lineMessageId && LineUnknownInboundMessage::query()->where('line_message_id', $lineMessageId)->exists()) {
            return;
        }

        $contact = CustomerLineContact::query()
            ->where('line_user_id', $lineUserId)
            ->first();

        if ($contact) {
            CustomerLineMessage::query()->create([
                'customer_line_contact_id' => $contact->id,
                'direction' => CustomerLineMessage::DIRECTION_INBOUND,
                'message_type' => 'text',
                'text' => $text,
                'line_message_id' => $lineMessageId,
                'payload' => null,
            ]);

            return;
        }

        try {
            LineUnknownInboundMessage::query()->create([
                'shop_id' => null,
                'line_user_id' => $lineUserId,
                'text' => $text,
                'line_message_id' => $lineMessageId,
                'raw_event' => $event,
                'created_at' => now(),
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate')) {
                return;
            }
            throw $e;
        }
    }
}
