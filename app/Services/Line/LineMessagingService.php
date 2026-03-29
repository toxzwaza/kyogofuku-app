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
}
