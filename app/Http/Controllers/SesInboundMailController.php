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
                    // MIME パース（文字列からストリームを作成してパース）
                    $stream = fopen('php://memory', 'r+');
                    fwrite($stream, $rawEmail);
                    rewind($stream);
                    $parser = new MailMimeParser();
                    // 第2引数にtrueを指定すると、パーサーがストリームを管理して閉じてくれる
                    $mimeMessage = $parser->parse($stream, true);
                    
                    // 基本情報
                    $subject = $mimeMessage->getHeaderValue('Subject');
                    $from = $mimeMessage->getHeaderValue('From');
                    $to = $mimeMessage->getHeaderValue('To');
                    $messageId = $mail['messageId'] ?? null;
                    
                    // 本文を取得
                    $textBody = $mimeMessage->getTextContent();
                    $htmlBody = $mimeMessage->getHtmlContent();
                    
                    // 添付ファイルを取得
                    $attachments = [];
                    $attachmentParts = $mimeMessage->getAllAttachmentParts();
                    Log::info('Found attachments', ['count' => count($attachmentParts)]);
                    
                    foreach ($attachmentParts as $index => $attachment) {
                        try {
                            $filename = $attachment->getFilename();
                            // ファイル名がない場合はデフォルト名を生成
                            if (empty($filename)) {
                                $mimeType = $attachment->getContentType() ?? 'application/octet-stream';
                                $extension = 'bin';
                                // 簡単なMIMEタイプから拡張子を推測
                                if (strpos($mimeType, 'image/') === 0) {
                                    $extension = explode('/', $mimeType)[1];
                                } elseif (strpos($mimeType, 'application/pdf') === 0) {
                                    $extension = 'pdf';
                                }
                                $filename = 'attachment_' . ($index + 1) . '_' . uniqid() . '.' . $extension;
                            }
                            
                            // コンテンツを取得
                            $content = $attachment->getContent();
                            if ($content === null || $content === false) {
                                Log::warning('Failed to get attachment content', [
                                    'filename' => $filename,
                                    'index' => $index,
                                ]);
                                continue;
                            }
                            
                            $attachments[] = [
                                'filename' => $filename,
                                'content' => $content,
                            ];
                            
                            Log::info('Attachment processed', [
                                'filename' => $filename,
                                'size' => strlen($content),
                            ]);
                        } catch (\Exception $e) {
                            Log::error('Error processing attachment', [
                                'index' => $index,
                                'error' => $e->getMessage(),
                            ]);
                            // エラーが発生しても処理を続行
                            continue;
                        }
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
                        try {
                            $filename = $attachment['filename'];
                            $content = $attachment['content'];
                            
                            // ストレージに保存
                            $storagePath = 'email-attachments/' . $email->id . '/' . $filename;
                            $saved = Storage::put($storagePath, $content);
                            
                            if (!$saved) {
                                Log::warning('Failed to save attachment to storage', [
                                    'filename' => $filename,
                                    'email_id' => $email->id,
                                ]);
                                continue;
                            }
                            
                            // データベースに保存
                            EmailAttachment::create([
                                'email_id' => $email->id,
                                'filename' => $filename,
                                'path' => $storagePath,
                            ]);
                            
                            $attachmentFilenames[] = $filename;
                            Log::info('Attachment saved', [
                                'filename' => $filename,
                                'email_id' => $email->id,
                                'path' => $storagePath,
                            ]);
                        } catch (\Exception $e) {
                            Log::error('Error saving attachment', [
                                'filename' => $attachment['filename'] ?? 'unknown',
                                'email_id' => $email->id,
                                'error' => $e->getMessage(),
                            ]);
                            // エラーが発生しても処理を続行
                            continue;
                        }
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
