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
                    $decodedContent = base64_decode($content, true);
                    if ($decodedContent === false) {
                        Log::warning('Failed to decode base64 content');
                        $decodedContent = '';
                    }
                }
                
                // デバッグ: デコードされたコンテンツの一部をログに出力
                if (!empty($decodedContent)) {
                    Log::debug('Decoded content preview', [
                        'preview' => substr($decodedContent, 0, 500),
                        'content_length' => strlen($decodedContent),
                    ]);
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
        if (preg_match('/Content-Type:\s*multipart\/alternative[^;]*boundary=["\']?([^"\'\s;]+)["\']?/i', $rawEmail, $matches)) {
            $boundary = $matches[1];
            Log::debug('Found multipart boundary', ['boundary' => $boundary]);
            
            // boundaryでパートを分割（最初と最後の空パートを除外）
            $parts = preg_split('/\r?\n--' . preg_quote($boundary, '/') . '(?:--)?\r?\n/', $rawEmail);
            
            foreach ($parts as $index => $part) {
                // 最初と最後のパートは通常ヘッダーなのでスキップ
                if ($index === 0 || empty(trim($part))) {
                    continue;
                }
                
                Log::debug('Processing part', ['index' => $index, 'part_preview' => substr($part, 0, 200)]);
                
                // text/plain を優先的に取得
                if (preg_match('/Content-Type:\s*text\/plain[^;]*charset=["\']?([^"\'\s;]+)["\']?/i', $part, $charsetMatch)) {
                    $charset = $charsetMatch[1] ?? 'UTF-8';
                    Log::debug('Found text/plain part', ['charset' => $charset]);
                    
                    // ヘッダー部分を除去して本文を取得
                    $bodyContent = preg_replace('/.*?\r?\n\r?\n/s', '', $part, 1);
                    $bodyContent = trim($bodyContent);
                    
                    if (preg_match('/Content-Transfer-Encoding:\s*base64/i', $part)) {
                        // BASE64エンコードされた本文をデコード
                        $decodedBody = base64_decode($bodyContent, true);
                        if ($decodedBody !== false) {
                            $body = $this->convertEncoding($decodedBody, $charset);
                            Log::debug('Decoded text/plain body', ['body_length' => strlen($body)]);
                            break; // text/plain が見つかったら終了
                        } else {
                            Log::warning('Failed to decode base64 text/plain');
                        }
                    } else {
                        // BASE64エンコードされていない場合
                        $body = $this->convertEncoding($bodyContent, $charset);
                        Log::debug('Extracted text/plain body', ['body_length' => strlen($body)]);
                        break;
                    }
                }
            }
            
            // text/plain が見つからなかった場合、text/html を取得
            if (empty($body)) {
                foreach ($parts as $index => $part) {
                    if ($index === 0 || empty(trim($part))) {
                        continue;
                    }
                    
                    if (preg_match('/Content-Type:\s*text\/html[^;]*charset=["\']?([^"\'\s;]+)["\']?/i', $part, $charsetMatch)) {
                        $charset = $charsetMatch[1] ?? 'UTF-8';
                        Log::debug('Found text/html part', ['charset' => $charset]);
                        
                        // ヘッダー部分を除去して本文を取得
                        $bodyContent = preg_replace('/.*?\r?\n\r?\n/s', '', $part, 1);
                        $bodyContent = trim($bodyContent);
                        
                        if (preg_match('/Content-Transfer-Encoding:\s*base64/i', $part)) {
                            $decodedBody = base64_decode($bodyContent, true);
                            if ($decodedBody !== false) {
                                $body = $this->convertEncoding($decodedBody, $charset);
                                // HTMLタグを除去してテキストのみにする
                                $body = strip_tags($body);
                                Log::debug('Decoded and stripped text/html body', ['body_length' => strlen($body)]);
                                break;
                            } else {
                                Log::warning('Failed to decode base64 text/html');
                            }
                        } else {
                            $body = strip_tags($this->convertEncoding($bodyContent, $charset));
                            Log::debug('Extracted and stripped text/html body', ['body_length' => strlen($body)]);
                            break;
                        }
                    }
                }
            }
        } else {
            // シンプルなテキストメールの場合
            if (preg_match('/Content-Type:\s*text\/plain[^;]*charset=["\']?([^"\'\s;]+)["\']?/i', $rawEmail, $charsetMatch)) {
                $charset = $charsetMatch[1] ?? 'UTF-8';
                
                // ヘッダー部分を除去して本文を取得
                $bodyContent = preg_replace('/.*?\r?\n\r?\n/s', '', $rawEmail, 1);
                $bodyContent = trim($bodyContent);
                
                if (preg_match('/Content-Transfer-Encoding:\s*base64/i', $rawEmail)) {
                    $decodedBody = base64_decode($bodyContent, true);
                    if ($decodedBody !== false) {
                        $body = $this->convertEncoding($decodedBody, $charset);
                    }
                } else {
                    $body = $this->convertEncoding($bodyContent, $charset);
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
