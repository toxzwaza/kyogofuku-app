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
                
                // メール本文を抽出
                $emailBody = $this->extractEmailBody($decodedContent);
                
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
                    'body' => $emailBody,
                ]);
            }
        }
    
        return response()->json(['status' => 'ok']);
    }
    
    /**
     * MIME形式のメールから本文を抽出
     */
    private function extractEmailBody($rawEmail)
    {
        if (empty($rawEmail)) {
            return '';
        }
        
        $body = '';
        
        // multipart/alternative の場合
        if (preg_match('/Content-Type:\s*multipart\/alternative[^;]*boundary="([^"]+)"/i', $rawEmail, $matches)) {
            $boundary = $matches[1];
            $parts = explode('--' . $boundary, $rawEmail);
            
            foreach ($parts as $part) {
                // text/plain を優先的に取得
                if (preg_match('/Content-Type:\s*text\/plain[^;]*charset="([^"]*)"/i', $part, $charsetMatch)) {
                    $charset = $charsetMatch[1] ?? 'UTF-8';
                    if (preg_match('/Content-Transfer-Encoding:\s*base64/i', $part)) {
                        // BASE64エンコードされた本文を抽出
                        $encodedBody = preg_replace('/.*?\r?\n\r?\n(.*?)(\r?\n--|\z)/s', '$1', $part);
                        $encodedBody = trim($encodedBody);
                        $decodedBody = base64_decode($encodedBody);
                        if ($decodedBody !== false) {
                            $body = $this->convertEncoding($decodedBody, $charset);
                            break; // text/plain が見つかったら終了
                        }
                    } else {
                        // BASE64エンコードされていない場合
                        $textBody = preg_replace('/.*?\r?\n\r?\n(.*?)(\r?\n--|\z)/s', '$1', $part);
                        $body = trim($textBody);
                        break;
                    }
                }
            }
            
            // text/plain が見つからなかった場合、text/html を取得
            if (empty($body)) {
                foreach ($parts as $part) {
                    if (preg_match('/Content-Type:\s*text\/html[^;]*charset="([^"]*)"/i', $part, $charsetMatch)) {
                        $charset = $charsetMatch[1] ?? 'UTF-8';
                        if (preg_match('/Content-Transfer-Encoding:\s*base64/i', $part)) {
                            $encodedBody = preg_replace('/.*?\r?\n\r?\n(.*?)(\r?\n--|\z)/s', '$1', $part);
                            $encodedBody = trim($encodedBody);
                            $decodedBody = base64_decode($encodedBody);
                            if ($decodedBody !== false) {
                                $body = $this->convertEncoding($decodedBody, $charset);
                                // HTMLタグを除去してテキストのみにする
                                $body = strip_tags($body);
                                break;
                            }
                        } else {
                            $htmlBody = preg_replace('/.*?\r?\n\r?\n(.*?)(\r?\n--|\z)/s', '$1', $part);
                            $body = strip_tags(trim($htmlBody));
                            break;
                        }
                    }
                }
            }
        } else {
            // シンプルなテキストメールの場合
            if (preg_match('/Content-Type:\s*text\/plain[^;]*charset="([^"]*)"/i', $rawEmail, $charsetMatch)) {
                $charset = $charsetMatch[1] ?? 'UTF-8';
                if (preg_match('/Content-Transfer-Encoding:\s*base64/i', $rawEmail)) {
                    $encodedBody = preg_replace('/.*?\r?\n\r?\n(.*?)(\r?\n--|\z)/s', '$1', $rawEmail);
                    $encodedBody = trim($encodedBody);
                    $decodedBody = base64_decode($encodedBody);
                    if ($decodedBody !== false) {
                        $body = $this->convertEncoding($decodedBody, $charset);
                    }
                } else {
                    $body = preg_replace('/.*?\r?\n\r?\n(.*?)(\r?\n--|\z)/s', '$1', $rawEmail);
                    $body = trim($body);
                }
            }
        }
        
        return $body;
    }
    
    /**
     * 文字エンコーディングを変換
     */
    private function convertEncoding($text, $fromCharset)
    {
        if (empty($text)) {
            return '';
        }
        
        // エンコーディングを正規化
        $fromCharset = strtoupper($fromCharset);
        if ($fromCharset === 'UTF-8' || $fromCharset === 'US-ASCII') {
            return $text;
        }
        
        // 文字エンコーディングを変換
        if (function_exists('mb_convert_encoding')) {
            $converted = @mb_convert_encoding($text, 'UTF-8', $fromCharset);
            if ($converted !== false) {
                return $converted;
            }
        }
        
        // 変換に失敗した場合はそのまま返す
        return $text;
    }
}
