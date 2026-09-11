<?php

namespace App\Services\Line;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LineMessagingService
{
    public function verifySignature(string $body, string $channelSecret, string $signatureHeader): bool
    {
        if ($signatureHeader === '' || $channelSecret === '') {
            return false;
        }

        $hash = hash_hmac('sha256', $body, $channelSecret, true);
        $expected = base64_encode($hash);

        return hash_equals($expected, $signatureHeader);
    }

    /**
     * 共通 Messaging API チャネルで 1:1 テキストを送信する
     *
     * @throws \RuntimeException
     */
    public function pushTextToUser(string $lineUserId, string $text): void
    {
        $token = (string) config('line.messaging.channel_access_token', '');
        if ($token === '') {
            throw new \RuntimeException('LINE Messaging API のチャネルアクセストークンが未設定です（.env の LINE_MESSAGING_CHANNEL_ACCESS_TOKEN）。');
        }

        $response = Http::withToken($token, 'Bearer')
            ->acceptJson()
            ->post('https://api.line.me/v2/bot/message/push', [
                'to' => $lineUserId,
                'messages' => [
                    [
                        'type' => 'text',
                        'text' => $text,
                    ],
                ],
            ]);

        if (! $response->successful()) {
            Log::warning('LINE push failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('LINE への送信に失敗しました: HTTP '.$response->status());
        }
    }

    /**
     * 共通 Messaging API チャネルで 1:1 画像を送信する
     *
     * 仕様: originalContentUrl / previewImageUrl とも HTTPS 必須、JPEG/PNG、original <= 1MB / 1024px
     *
     * @throws \RuntimeException
     */
    public function pushImageToUser(string $lineUserId, string $originalContentUrl, string $previewImageUrl): void
    {
        $token = (string) config('line.messaging.channel_access_token', '');
        if ($token === '') {
            throw new \RuntimeException('LINE Messaging API のチャネルアクセストークンが未設定です（.env の LINE_MESSAGING_CHANNEL_ACCESS_TOKEN）。');
        }

        $requestPayload = [
            'to' => $lineUserId,
            'messages' => [
                [
                    'type' => 'image',
                    'originalContentUrl' => $originalContentUrl,
                    'previewImageUrl' => $previewImageUrl,
                ],
            ],
        ];

        Log::channel('line_image')->debug('[push] image push request', [
            'line_user_id' => $lineUserId,
            'original_url' => $originalContentUrl,
            'preview_url' => $previewImageUrl,
        ]);

        try {
            $response = Http::withToken($token, 'Bearer')
                ->acceptJson()
                ->timeout(10)
                ->post('https://api.line.me/v2/bot/message/push', $requestPayload);
        } catch (\Throwable $e) {
            Log::channel('line_image')->error('[push] HTTP exception while pushing image', [
                'line_user_id' => $lineUserId,
                'exception' => $e::class.': '.$e->getMessage(),
            ]);
            throw new \RuntimeException('LINE への画像送信中に HTTP 例外: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            Log::channel('line_image')->error('[push] image push failed', [
                'line_user_id' => $lineUserId,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 1000),
                'request' => $requestPayload,
            ]);
            throw new \RuntimeException('LINE への画像送信に失敗しました: HTTP '.$response->status());
        }

        Log::channel('line_image')->info('[push] image push success', [
            'line_user_id' => $lineUserId,
        ]);
    }

    /**
     * 共通 Messaging API チャネルで複数ユーザーへ同一メッセージを一括送信する（Multicast API）
     *
     * 仕様: to は最大500件、messages は最大5件。呼び出し側で500件以下にチャンクすること。
     *
     * @param  array<int, string>  $lineUserIds
     * @param  array<int, array<string, mixed>>  $messages  LINE メッセージオブジェクトの配列
     * @throws \RuntimeException
     */
    public function multicast(array $lineUserIds, array $messages): void
    {
        $token = (string) config('line.messaging.channel_access_token', '');
        if ($token === '') {
            throw new \RuntimeException('LINE Messaging API のチャネルアクセストークンが未設定です（.env の LINE_MESSAGING_CHANNEL_ACCESS_TOKEN）。');
        }

        if (count($lineUserIds) === 0) {
            return;
        }
        if (count($lineUserIds) > 500) {
            throw new \RuntimeException('Multicast の宛先は最大500件です（'.count($lineUserIds).'件が指定されました）。');
        }

        try {
            $response = Http::withToken($token, 'Bearer')
                ->acceptJson()
                ->timeout(20)
                ->post('https://api.line.me/v2/bot/message/multicast', [
                    'to' => array_values($lineUserIds),
                    'messages' => $messages,
                ]);
        } catch (\Throwable $e) {
            Log::warning('LINE multicast HTTP exception', [
                'to_count' => count($lineUserIds),
                'exception' => $e::class.': '.$e->getMessage(),
            ]);
            throw new \RuntimeException('LINE への一括送信中に HTTP 例外: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            Log::warning('LINE multicast failed', [
                'to_count' => count($lineUserIds),
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 1000),
            ]);
            throw new \RuntimeException('LINE への一括送信に失敗しました: HTTP '.$response->status());
        }
    }

    /**
     * 共通 Messaging API チャネルで 1:1 で任意のメッセージ配列を送信する
     *
     * @param  array<int, array<string, mixed>>  $messages  LINE メッセージオブジェクトの配列（最大5件）
     * @throws \RuntimeException
     */
    public function pushMessagesToUser(string $lineUserId, array $messages): void
    {
        $token = (string) config('line.messaging.channel_access_token', '');
        if ($token === '') {
            throw new \RuntimeException('LINE Messaging API のチャネルアクセストークンが未設定です（.env の LINE_MESSAGING_CHANNEL_ACCESS_TOKEN）。');
        }

        $response = Http::withToken($token, 'Bearer')
            ->acceptJson()
            ->timeout(15)
            ->post('https://api.line.me/v2/bot/message/push', [
                'to' => $lineUserId,
                'messages' => $messages,
            ]);

        if (! $response->successful()) {
            Log::warning('LINE push (messages) failed', [
                'line_user_id' => $lineUserId,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 1000),
            ]);
            throw new \RuntimeException('LINE への送信に失敗しました: HTTP '.$response->status());
        }
    }

    /**
     * Webhook で受信した画像メッセージのバイナリを LINE API から取得
     *
     * 仕様: 送信から約10分以内に取得する必要あり (期限切れは 410)
     *
     * @return array{contents:string, mime:string, ext:string, size:int}
     * @throws \RuntimeException
     */
    public function fetchInboundImage(string $messageId): array
    {
        $token = (string) config('line.messaging.channel_access_token', '');
        if ($token === '') {
            throw new \RuntimeException('LINE Messaging API のチャネルアクセストークンが未設定です（.env の LINE_MESSAGING_CHANNEL_ACCESS_TOKEN）。');
        }

        $url = 'https://api-data.line.me/v2/bot/message/'.$messageId.'/content';
        Log::channel('line_image')->debug('[fetch] inbound image fetch start', [
            'message_id' => $messageId,
            'url' => $url,
        ]);

        try {
            $response = Http::withToken($token, 'Bearer')
                ->timeout(20)
                ->get($url);
        } catch (\Throwable $e) {
            Log::channel('line_image')->error('[fetch] HTTP exception while fetching content', [
                'message_id' => $messageId,
                'exception' => $e::class.': '.$e->getMessage(),
            ]);
            throw new \RuntimeException('LINE からの画像取得中に HTTP 例外: '.$e->getMessage(), 0, $e);
        }

        if (! $response->successful()) {
            Log::channel('line_image')->error('[fetch] inbound image fetch failed', [
                'message_id' => $messageId,
                'status' => $response->status(),
                'body' => mb_substr($response->body(), 0, 500),
            ]);
            throw new \RuntimeException('LINE からの画像取得に失敗しました: HTTP '.$response->status());
        }

        $contents = $response->body();
        $mime = $response->header('Content-Type') ?: 'application/octet-stream';
        // image/jpeg → jpg、image/png → png 等
        $ext = match (strtolower(explode(';', $mime)[0] ?? '')) {
            'image/jpeg', 'image/jpg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => 'bin',
        };

        $size = strlen($contents);
        Log::channel('line_image')->info('[fetch] inbound image fetch success', [
            'message_id' => $messageId,
            'mime' => $mime,
            'ext' => $ext,
            'size' => $size,
        ]);

        return [
            'contents' => $contents,
            'mime' => $mime,
            'ext' => $ext,
            'size' => $size,
        ];
    }
}
