<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\EmailAttachment;
use App\Models\EmailThread;
use App\Models\EventReservation;
use App\Http\Controllers\LineWebhookController;
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
                    
                    // SESのSMTP idを保存（参考用）
                    $sesSmtpId = $mail['messageId'] ?? null;
                    
                    // raw_emailのヘッダーからMessage-IDを抽出（重要：SESのSMTP idではなく、実際のメールヘッダーのMessage-IDを使用）
                    $messageId = $mimeMessage->getHeaderValue('Message-ID');
                    if (!$messageId) {
                        // ヘッダーから取得できない場合、raw_emailから正規表現で抽出
                        if (preg_match('/^Message-ID:\s*(<[^>]+>)/mi', $rawEmail, $matches)) {
                            $messageId = trim($matches[1]);
                        }
                    }
                    
                    // In-Reply-Toヘッダーを抽出
                    $inReplyTo = $mimeMessage->getHeaderValue('In-Reply-To');
                    if (!$inReplyTo) {
                        // ヘッダーから取得できない場合、raw_emailから正規表現で抽出
                        if (preg_match('/^In-Reply-To:\s*(<[^>]+>)/mi', $rawEmail, $matches)) {
                            $inReplyTo = trim($matches[1]);
                        }
                    }
                    
                    // Referencesヘッダーを抽出（複数のMessage-IDがスペース区切りで含まれる可能性がある）
                    $references = null;
                    try {
                        $referencesHeader = $mimeMessage->getHeader('References');
                        if ($referencesHeader) {
                            $references = $referencesHeader->getValue();
                        }
                    } catch (\Exception $e) {
                        // ヘッダー取得に失敗した場合、raw_emailから正規表現で抽出
                    }
                    
                    if (!$references) {
                        // ヘッダーから取得できない場合、raw_emailから正規表現で抽出
                        // 複数行にまたがる可能性があるため、改行とスペースを処理
                        if (preg_match('/^References:\s*((?:<[^>]+>\s*)+)/mi', $rawEmail, $matches)) {
                            $references = trim($matches[1]);
                            // 複数行にまたがる場合の処理（改行と連続するスペースを単一スペースに）
                            $references = preg_replace('/\s+/', ' ', $references);
                        }
                    }
                    
                    // デバッグ用ログ：抽出したヘッダー情報
                    Log::info('受信メール - ヘッダー抽出結果', [
                        'ses_smtp_id' => $sesSmtpId,
                        'message_id_from_header' => $messageId,
                        'in_reply_to' => $inReplyTo,
                        'references' => $references,
                        'message_id_format_check' => $messageId ? (preg_match('/^<.*@.*>$/', $messageId) ? 'RFC 5322形式（正常）' : '形式不正') : 'null',
                    ]);
                    
                    // 件名からスレッド番号を抽出
                    $emailThreadId = null;
                    $eventReservationId = null;
                    if (preg_match('/\[(\d+)\]/', $subject, $matches)) {
                        $emailThreadId = (int)$matches[1];
                        
                        // スレッドIDからevent_reservation_idを取得
                        $emailThread = EmailThread::find($emailThreadId);
                        if ($emailThread) {
                            $eventReservationId = $emailThread->event_reservation_id;
                            Log::info('スレッド番号から予約を特定', [
                                'email_thread_id' => $emailThreadId,
                                'event_reservation_id' => $eventReservationId,
                            ]);
                        } else {
                            Log::warning('スレッドが見つかりません', [
                                'email_thread_id' => $emailThreadId,
                            ]);
                        }
                    } else {
                        Log::info('件名にスレッド番号が含まれていません', [
                            'subject' => $subject,
                        ]);
                    }
                    
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
                    
                    // デバッグ用：受信したメールの生データをログに記録
                    // raw_emailは長いので、最初の1000文字のみ記録
                    Log::info('受信メール取得 - raw_email（ヘッダー部分）', [
                        'message_id' => $messageId,
                        'ses_smtp_id' => $sesSmtpId,
                        'from' => $from,
                        'to' => $to,
                        'subject' => $subject,
                        'in_reply_to' => $inReplyTo,
                        'references' => $references,
                        'raw_email_header_preview' => substr($rawEmail, 0, 1000),
                    ]);
                    
                    // メールをデータベースに保存
                    $email = Email::create([
                        'message_id' => $messageId, // 実際のメールヘッダーのMessage-ID
                        'ses_smtp_id' => $sesSmtpId, // SESのSMTP id（参考用）
                        'from' => $from,
                        'to' => $to,
                        'subject' => $subject,
                        'in_reply_to' => $inReplyTo,
                        'references' => $references,
                        'event_reservation_id' => $eventReservationId,
                        'email_thread_id' => $emailThreadId,
                        'text_body' => $textBody,
                        'html_body' => $htmlBody,
                        'raw_email' => $rawEmail,
                    ]);
                    
                    // デバッグ用：保存後のemail_idをログに記録
                    Log::info('受信メール保存完了', [
                        'email_id' => $email->id,
                        'message_id' => $messageId,
                        'ses_smtp_id' => $sesSmtpId,
                        'in_reply_to' => $inReplyTo,
                        'references' => $references,
                    ]);
                    
                    // 添付ファイルを保存
                    $attachmentFilenames = [];
                    foreach ($attachments as $attachment) {
                        try {
                            $filename = $attachment['filename'];
                            $content = $attachment['content'];
                            
                            // ストレージに保存（明示的に local。将来 S3 へ移行する場合は disk('s3_private') 等に変更）
                            $storagePath = 'email-attachments/' . $email->id . '/' . $filename;
                            $saved = Storage::disk('local')->put($storagePath, $content);
                            
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
                    
                    // LINE通知を送信（event_reservation_idが取得できている場合）
                    if ($eventReservationId) {
                        try {
                            $this->sendLineNotificationForEmail($email, $eventReservationId, $from, $subject, $textBody, $attachmentFilenames);
                        } catch (\Exception $e) {
                            // LINE通知のエラーはログに記録するが、メール処理は続行
                            Log::error('Failed to send LINE notification for email', [
                                'email_id' => $email->id,
                                'event_reservation_id' => $eventReservationId,
                                'error' => $e->getMessage(),
                            ]);
                        }
                    }
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
    
    /**
     * メール受信時にLINE通知を送信
     */
    private function sendLineNotificationForEmail(Email $email, $eventReservationId, $from, $subject, $textBody, $attachmentFilenames)
    {
        // EventReservationを取得
        $reservation = EventReservation::with(['event.shops'])->find($eventReservationId);
        if (!$reservation || !$reservation->event) {
            Log::warning('EventReservation or Event not found for LINE notification', [
                'event_reservation_id' => $eventReservationId,
            ]);
            return;
        }
        
        $event = $reservation->event;
        
        // イベントに紐づく店舗を取得
        $shops = $event->shops;
        if ($shops->isEmpty()) {
            Log::info('No shops found for event, skipping LINE notification', [
                'event_id' => $event->id,
            ]);
            return;
        }
        
        // メッセージを構築
        $message = "━━━━━━━━━━━━━━━━\n";
        $message .= "📧 メール受信通知\n";
        $message .= "━━━━━━━━━━━━━━━━\n\n";
        
        $message .= "🎯 イベント名: {$event->title}\n";
        $message .= "👤 お客様名: {$reservation->name}\n\n";
        
        $message .= "━━━━━━━━━━━━━━━━\n";
        $message .= "📨 メール情報\n";
        $message .= "━━━━━━━━━━━━━━━━\n";
        $message .= "送信者: {$from}\n";
        $message .= "件名: {$subject}\n";
        
        // 本文の最初の200文字を表示（改行を削除）
        $bodyPreview = mb_substr(str_replace(["\r\n", "\r", "\n"], ' ', $textBody), 0, 200);
        if (mb_strlen($textBody) > 200) {
            $bodyPreview .= '...';
        }
        $message .= "本文: {$bodyPreview}\n";
        
        // 添付ファイルがある場合
        if (!empty($attachmentFilenames)) {
            $message .= "添付ファイル: " . implode('、', $attachmentFilenames) . "\n";
        }
        
        $message .= "\n━━━━━━━━━━━━━━━━\n";
        $message .= "予約ID: #{$reservation->id}\n";
        $message .= "メールID: #{$email->id}\n";
        $message .= "━━━━━━━━━━━━━━━━";
        
        // 各店舗のLINEグループに通知を送信
        $lineController = new LineWebhookController();
        
        // 送信済みのline_group_idを記録する配列
        $sentGroupIds = [];
        
        foreach ($shops as $shop) {
            if (!empty($shop->line_group_id)) {
                // 同じline_group_idに既に送信済みの場合はスキップ
                if (in_array($shop->line_group_id, $sentGroupIds)) {
                    Log::info('同じLINEグループIDに既に通知を送信済みのためスキップ', [
                        'email_id' => $email->id,
                        'shop_id' => $shop->id,
                        'shop_name' => $shop->name,
                        'line_group_id' => $shop->line_group_id,
                    ]);
                    continue;
                }
                
                try {
                    $lineController->pushToLineGroup($message, $shop->line_group_id);
                    // 送信済みのline_group_idを記録
                    $sentGroupIds[] = $shop->line_group_id;
                    Log::info('LINE notification sent for email', [
                        'email_id' => $email->id,
                        'shop_id' => $shop->id,
                        'shop_name' => $shop->name,
                        'line_group_id' => $shop->line_group_id,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to send LINE notification to shop', [
                        'email_id' => $email->id,
                        'shop_id' => $shop->id,
                        'shop_name' => $shop->name,
                        'line_group_id' => $shop->line_group_id,
                        'error' => $e->getMessage(),
                    ]);
                    // エラーが発生しても他の店舗への送信は続行
                }
            } else {
                Log::info('Shop does not have line_group_id, skipping LINE notification', [
                    'shop_id' => $shop->id,
                    'shop_name' => $shop->name,
                ]);
            }
        }
    }
}
