<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LineWebhookController extends Controller
{
    public function handle(Request $request)
    {
        $event = $request->input('events')[0];

        // グループID取得（初回メッセージ時）
        if ($event['source']['type'] === 'group') {
            $groupId = $event['source']['groupId'];

            // ここでDBに保存
            Log::info('LINE groupId = ' . $groupId);
        }

        return response()->json(['status' => 'ok']);
    }

    public function pushToLineGroup($message, $groupId = null)
    {
        $channelToken = env('LINE_CHANNEL_TOKEN');
        
        // グループIDが引数で渡されていない場合は、.envから取得（後方互換性のため）
        if (empty($groupId)) {
        $groupId = env('LINE_GROUP_ID');
        }

        // トークンとグループIDが設定されているか確認
        if (empty($channelToken)) {
            Log::error('LINE_CHANNEL_TOKEN is not set in .env file');
            throw new \Exception('LINE_CHANNEL_TOKEN is not configured');
        }

        if (empty($groupId)) {
            Log::error('LINE_GROUP_ID is not provided and not set in .env file');
            throw new \Exception('LINE_GROUP_ID is not configured');
        }

        $http = new \GuzzleHttp\Client([
            'verify' => false, // SSL証明書の検証を無効化（開発環境用）
        ]);

        try {
            $response = $http->post('https://api.line.me/v2/bot/message/push', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer ' . $channelToken,
                ],
                'json' => [
                    'to' => $groupId,
                    'messages' => [
                        [
                            'type' => 'text',
                            'text' => $message,
                        ]
                    ]
                ]
            ]);

            Log::info('LINE push notification sent successfully', [
                'status' => $response->getStatusCode(),
            ]);

            return $response;
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $statusCode = $response ? $response->getStatusCode() : 'unknown';
            $body = $response ? $response->getBody()->getContents() : 'unknown';
            
            Log::error('LINE push notification failed', [
                'status_code' => $statusCode,
                'error_body' => $body,
                'token_preview' => substr($channelToken, 0, 10) . '...', // セキュリティのため最初の10文字のみ
            ]);

            throw $e;
        } catch (\Exception $e) {
            Log::error('LINE push notification error: ' . $e->getMessage());
            throw $e;
        }
    }
}
