<?php

namespace App\Services\Line;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LineIdTokenVerifier
{
    /**
     * JWT ペイロードの aud（検証失敗時のログ用。署名は検証しない）
     */
    private function decodeJwtAudience(string $idToken): ?string
    {
        $parts = explode('.', $idToken);
        if (count($parts) < 2) {
            return null;
        }
        $b64 = strtr($parts[1], '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad > 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        $raw = base64_decode($b64, true);
        if ($raw === false) {
            return null;
        }
        $payload = json_decode($raw, true);

        return is_array($payload) && isset($payload['aud'])
            ? (string) $payload['aud']
            : null;
    }

    /**
     * @return array{sub: string}|null sub = LINE ユーザーID
     */
    public function verify(string $idToken, string $channelId): ?array
    {
        if ($idToken === '' || $channelId === '') {
            return null;
        }

        $response = Http::asForm()
            ->post('https://api.line.me/oauth2/v2.1/verify', [
                'id_token' => $idToken,
                'client_id' => $channelId,
            ]);

        if (! $response->successful()) {
            $json = $response->json();
            Log::warning('LINE id_token verify failed', [
                'status' => $response->status(),
                'client_id_sent' => $channelId,
                'id_token_aud' => $this->decodeJwtAudience($idToken),
                'error' => is_array($json) ? ($json['error'] ?? null) : null,
                'error_description' => is_array($json) ? ($json['error_description'] ?? null) : null,
                'body' => $response->body(),
            ]);

            return null;
        }

        $json = $response->json();
        if (! is_array($json) || empty($json['sub'])) {
            return null;
        }

        return ['sub' => (string) $json['sub']];
    }
}
