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

    /**
     * LINEグループにメッセージを送信
     *
     * @param string $message テキストメッセージ
     * @param string|null $groupId グループID（nullの場合は.envのLINE_GROUP_IDを使用）
     * @param string|null $actionUrl オプション。指定時は「URIボタン」を追加送信し、タップで確実にURLを開ける
     * @param string $actionLabel ボタンラベル（actionUrl指定時のみ、最大20文字）
     */
    public function pushToLineGroup($message, $groupId = null, $actionUrl = null, $actionLabel = '詳細を開く')
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

        // URL指定時は1通のFlexメッセージ（本文＋URIボタン）にまとめてAPI使用料を節約
        if (!empty($actionUrl)) {
            $messages = [
                [
                    'type' => 'flex',
                    'altText' => '新しい予約が届きました',
                    'contents' => [
                        'type' => 'bubble',
                        'body' => [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'contents' => [
                                [
                                    'type' => 'text',
                                    'text' => $message,
                                    'wrap' => true,
                                ]
                            ]
                        ],
                        'footer' => [
                            'type' => 'box',
                            'layout' => 'vertical',
                            'contents' => [
                                [
                                    'type' => 'button',
                                    'style' => 'primary',
                                    'action' => [
                                        'type' => 'uri',
                                        'label' => mb_substr($actionLabel, 0, 20),
                                        'uri' => $actionUrl,
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]
            ];
        } else {
            $messages = [
                [
                    'type' => 'text',
                    'text' => $message,
                ]
            ];
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
                    'messages' => $messages,
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
