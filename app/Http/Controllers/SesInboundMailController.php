<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\EmailAttachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
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
                    // MIME パース（ストリーム経由）
                    $stream = fopen('php://memory', 'r+');
                    fwrite($stream, $rawEmail);
                    rewind($stream);
                    $parser = new MailMimeParser();
                    $mimeMessage = $parser->parse($stream, false);
                    fclose($stream);
                    
                    // 基本情報
                    $subject = $mimeMessage->getHeaderValue('Subject');
                    $from = $mimeMessage->getHeaderValue('From');
                    $to = $mimeMessage->getHeaderValue('To');
                    $messageId = $mail['messageId'] ?? null;
                    
                    // 本文
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
                    
                    // メールをデータベースに保存
                    $email = Email::create([
                        'message_id' => $messageId,
                        'from' => $from,
                        'to' => $to,
                        'subject' => $subject,
                        'text_body' => $textBody,
                        'html_body' => $htmlBody,
                        'raw_email' => $rawEmail,
                    ]);
                    
                    // 添付ファイルを保存
                    $attachmentFilenames = [];
                    foreach ($attachments as $attachment) {
                        $filename = $attachment['filename'];
                        if (empty($filename)) {
                            // ファイル名がない場合はデフォルト名を生成
                            $filename = 'attachment_' . uniqid() . '.bin';
                        }
                        
                        // ストレージに保存
                        $storagePath = 'email-attachments/' . $email->id . '/' . $filename;
                        Storage::put($storagePath, $attachment['content']);
                        
                        // データベースに保存
                        EmailAttachment::create([
                            'email_id' => $email->id,
                            'filename' => $filename,
                            'path' => $storagePath,
                        ]);
                        
                        $attachmentFilenames[] = $filename;
                    }
                    
                    // ログ出力
                    Log::info('Amazon SES Inbound Mail Parsed', [
                        'email_id' => $email->id,
                        'message_id' => $messageId,
                        'from' => $from,
                        'to' => $to,
                        'subject' => $subject,
                        'text_body' => $textBody,
                        'html_body' => $htmlBody,
                        'attachments' => $attachmentFilenames,
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
