<?php

namespace App\Http\Controllers;

use App\Models\CustomerLineContact;
use App\Models\CustomerLineMessage;
use App\Models\LineUnknownInboundMessage;
use App\Services\Line\LineMessageMediaStore;
use App\Services\Line\LineMessagingService;
use App\Services\Line\ShopLineGroupNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LineMessagingWebhookController extends Controller
{
    public function __construct(
        private LineMessagingService $lineMessaging,
        private ShopLineGroupNotifier $shopNotifier,
        private LineMessageMediaStore $mediaStore,
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

        if ($type === 'follow' || $type === 'unfollow') {
            $this->processFollowEvent($event, $type);

            return;
        }

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
        $lineMessageIdRaw = isset($message['id']) ? (string) $message['id'] : null;

        // 画像メッセージ：機能フラグ ON のときだけ処理。OFF や失敗時は従来通り unknown へ記録
        if ($messageType === 'image' && config('line.image_messaging.enabled', true)) {
            $this->processInboundImage($event, $lineUserId, $message, $lineMessageIdRaw);

            return;
        }

        if ($messageType !== 'text') {
            LineUnknownInboundMessage::query()->create([
                'shop_id' => null,
                'line_user_id' => $lineUserId,
                'text' => null,
                'line_message_id' => $lineMessageIdRaw,
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

            // 連携済みユーザーからのテキスト受信時は店舗グループにも通知
            $this->shopNotifier->notifyInboundMessage($contact, $text);

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

    /**
     * 画像メッセージを処理する。
     *
     * - LINE API から content を同期DLし S3 public へ保存
     * - 連携済みユーザーなら CustomerLineMessage(type=image, media_file_id) として記録、店舗グループへ通知
     * - 未連携 or 失敗時は LineUnknownInboundMessage に記録（処理続行のため例外を握りつぶす）
     */
    private function processInboundImage(array $event, string $lineUserId, array $message, ?string $lineMessageId): void
    {
        if (! $lineMessageId) {
            Log::channel('line_image')->warning('[inbound] image event without message id', [
                'line_user_id' => $lineUserId,
                'event' => $event,
            ]);

            return;
        }

        // 重複Webhook対策
        if (CustomerLineMessage::query()->where('line_message_id', $lineMessageId)->exists()) {
            Log::channel('line_image')->debug('[inbound] duplicate (already in CustomerLineMessage)', [
                'line_message_id' => $lineMessageId,
            ]);

            return;
        }
        if (LineUnknownInboundMessage::query()->where('line_message_id', $lineMessageId)->exists()) {
            Log::channel('line_image')->debug('[inbound] duplicate (already in LineUnknownInboundMessage)', [
                'line_message_id' => $lineMessageId,
            ]);

            return;
        }

        Log::channel('line_image')->info('[inbound] image message received', [
            'line_message_id' => $lineMessageId,
            'line_user_id' => $lineUserId,
            'message' => $message,
        ]);

        $mediaFile = null;
        try {
            $mediaFile = $this->mediaStore->storeInboundImage($lineMessageId, $lineUserId);
        } catch (\Throwable $e) {
            Log::channel('line_image')->error('[inbound] failed to store image, falling back to unknown record', [
                'line_message_id' => $lineMessageId,
                'line_user_id' => $lineUserId,
                'exception' => $e::class.': '.$e->getMessage(),
            ]);
            // 失敗時はメタだけ unknown に残す（Webhook は 200 を返したいので例外は握りつぶす）
            try {
                LineUnknownInboundMessage::query()->create([
                    'shop_id' => null,
                    'line_user_id' => $lineUserId,
                    'text' => '[画像受信失敗]',
                    'line_message_id' => $lineMessageId,
                    'raw_event' => $event,
                    'created_at' => now(),
                ]);
            } catch (\Throwable $e2) {
                // ここで失敗してもログだけ残して続行
                Log::channel('line_image')->error('[inbound] also failed to record unknown fallback', [
                    'line_message_id' => $lineMessageId,
                    'exception' => $e2::class.': '.$e2->getMessage(),
                ]);
            }

            return;
        }

        $contact = CustomerLineContact::query()
            ->where('line_user_id', $lineUserId)
            ->first();

        if ($contact) {
            CustomerLineMessage::query()->create([
                'customer_line_contact_id' => $contact->id,
                'direction' => CustomerLineMessage::DIRECTION_INBOUND,
                'message_type' => 'image',
                'text' => null,
                'line_message_id' => $lineMessageId,
                'payload' => $message,
                'media_file_id' => $mediaFile->id,
            ]);

            // 店舗グループには「画像が届いた」旨を通知
            try {
                $this->shopNotifier->notifyInboundMessage($contact, '[画像が届きました]');
            } catch (\Throwable $e) {
                Log::channel('line_image')->warning('[inbound] shop group notify failed', [
                    'line_message_id' => $lineMessageId,
                    'exception' => $e::class.': '.$e->getMessage(),
                ]);
            }

            return;
        }

        // 未連携ユーザー：unknown に保存（画像参照付き）
        try {
            LineUnknownInboundMessage::query()->create([
                'shop_id' => null,
                'line_user_id' => $lineUserId,
                'text' => '[画像] media_file_id='.$mediaFile->id,
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

    /**
     * follow / unfollow イベントを記録する。
     *
     * 友だち追加（follow）後、まだ顧客・予約に紐付いていないユーザーを追跡できるようにする。
     * あいさつメッセージは LINE Official Account Manager の管理画面で固定送信されるため、
     * ここでは Push せず、追跡用にログ記録のみ行う。
     */
    private function processFollowEvent(array $event, string $type): void
    {
        $source = $event['source'] ?? [];
        if (($source['type'] ?? '') !== 'user' || empty($source['userId'])) {
            return;
        }

        $lineUserId = (string) $source['userId'];

        try {
            LineUnknownInboundMessage::query()->create([
                'shop_id' => null,
                'line_user_id' => $lineUserId,
                'text' => '('.$type.' event)',
                'line_message_id' => null,
                'raw_event' => $event,
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('LINE webhook: failed to record follow event', [
                'type' => $type,
                'line_user_id' => $lineUserId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
