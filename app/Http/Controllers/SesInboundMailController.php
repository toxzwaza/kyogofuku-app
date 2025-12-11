<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SesInboundMailController extends Controller
{
    public function handle(Request $request)
    {
        // 生の JSON を取得
        $raw = $request->getContent();
        $data = json_decode($raw, true);
    
        // SNSの確認
        if (!empty($data['Type']) && $data['Type'] === 'SubscriptionConfirmation') {
            Log::info('SNS Subscription Confirmation', [
                'subscribe_url' => $data['SubscribeURL'] ?? null,
            ]);
            return response()->json(['status' => 'ok']);
        }
    
        // Notificationタイプの場合
        if (!empty($data['Type']) && $data['Type'] === 'Notification') {
            // Messageフィールドをさらにデコード
            $message = json_decode($data['Message'] ?? '{}', true);
            
            if (!empty($message['notificationType']) && $message['notificationType'] === 'Received') {
                $mail = $message['mail'] ?? [];
                $receipt = $message['receipt'] ?? [];
                $content = $message['content'] ?? '';
                
                // メールの基本情報を抽出
                $commonHeaders = $mail['commonHeaders'] ?? [];
                
                // メール本文をBASE64デコード
                $decodedContent = '';
                if (!empty($content)) {
                    $decodedContent = base64_decode($content);
                }
                
                // 構造化されたログを出力
                Log::info('Amazon SES Inbound Mail Received', [
                    'message_id' => $mail['messageId'] ?? null,
                    'source' => $mail['source'] ?? null,
                    'destination' => $mail['destination'] ?? [],
                    'timestamp' => $mail['timestamp'] ?? null,
                    'from' => $commonHeaders['from'] ?? [],
                    'to' => $commonHeaders['to'] ?? [],
                    'subject' => $commonHeaders['subject'] ?? null,
                    'date' => $commonHeaders['date'] ?? null,
                    'spam_verdict' => $receipt['spamVerdict']['status'] ?? null,
                    'virus_verdict' => $receipt['virusVerdict']['status'] ?? null,
                    'spf_verdict' => $receipt['spfVerdict']['status'] ?? null,
                    'dkim_verdict' => $receipt['dkimVerdict']['status'] ?? null,
                    'dmarc_verdict' => $receipt['dmarcVerdict']['status'] ?? null,
                    'content_preview' => !empty($decodedContent) ? substr($decodedContent, 0, 500) : null,
                ]);
                
                // メール本文全体も別途ログに出力（必要に応じて）
                if (!empty($decodedContent)) {
                    Log::info('Email Content', [
                        'content' => $decodedContent,
                    ]);
                }
            }
        }
    
        return response()->json(['status' => 'ok']);
    }
}
