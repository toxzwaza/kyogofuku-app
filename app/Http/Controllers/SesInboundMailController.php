<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use ZBateson\MailMimeParser\MailMimeParser;

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
                
                // BASE64デコード
                $rawEmail = base64_decode($content, true);
                if ($rawEmail === false) {
                    Log::warning('Failed to decode base64 content');
                    return response()->json(['status' => 'error', 'message' => 'Failed to decode content']);
                }
                
                try {
                    // MIME パース（文字列から一時ストリームを作成）
                    $stream = fopen('php://memory', 'r+');
                    fwrite($stream, $rawEmail);
                    rewind($stream);
                    $parser = new MailMimeParser();
                    $mimeMessage = $parser->parse($stream, true);
                    fclose($stream);
                    
                    // 基本情報
                    $subject = $mimeMessage->getHeaderValue('Subject');
                    $from = $mimeMessage->getHeaderValue('From');
                    $to = $mimeMessage->getHeaderValue('To');
                    
                    // 本文（txt / html）
                    $textBody = $mimeMessage->getTextContent();
                    $htmlBody = $mimeMessage->getHtmlContent();
                    
                    // 添付ファイル
                    $attachments = [];
                    foreach ($mimeMessage->getAllAttachmentParts() as $attachment) {
                        $attachments[] = [
                            'filename' => $attachment->getFilename(),
                            'content' => $attachment->getContent(),
                        ];
                    }
                    
                    // ログ出力
                    Log::info('Amazon SES Inbound Mail Received', [
                        'message_id' => $mail['messageId'] ?? null,
                        'source' => $mail['source'] ?? null,
                        'destination' => $mail['destination'] ?? [],
                        'timestamp' => $mail['timestamp'] ?? null,
                        'from' => $from,
                        'to' => $to,
                        'subject' => $subject,
                        'date' => $mail['commonHeaders']['date'] ?? null,
                        'spam_verdict' => $receipt['spamVerdict']['status'] ?? null,
                        'virus_verdict' => $receipt['virusVerdict']['status'] ?? null,
                        'spf_verdict' => $receipt['spfVerdict']['status'] ?? null,
                        'dkim_verdict' => $receipt['dkimVerdict']['status'] ?? null,
                        'dmarc_verdict' => $receipt['dmarcVerdict']['status'] ?? null,
                        'text_body' => $textBody,
                        'html_body' => $htmlBody,
                        'attachments' => array_column($attachments, 'filename'),
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to parse email', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    return response()->json(['status' => 'error', 'message' => 'Failed to parse email']);
                }
            }
        }
    
        return response()->json(['status' => 'ok']);
    }
}
