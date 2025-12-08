<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PostalCodeController extends Controller
{
    /**
     * 郵便番号から住所を取得
     */
    public function search(Request $request)
    {
        $postalCode = $request->input('postal_code');
        
        // ハイフンを除去
        $postalCode = str_replace('-', '', $postalCode);
        
        if (strlen($postalCode) !== 7) {
            return response()->json(['error' => '郵便番号は7桁で入力してください。'], 400);
        }
        
        // 郵便番号検索API（郵便番号データダウンロード - 郵便事業株式会社）
        try {
            // 開発環境ではSSL証明書の検証を無効化（本番環境では削除推奨）
            $httpClient = Http::timeout(10)->retry(2, 100);
            
            // Windows環境でのSSL証明書エラーを回避（開発環境のみ）
            if (app()->environment('local')) {
                $httpClient = $httpClient->withoutVerifying();
            }
            
            $response = $httpClient->get("https://zipcloud.ibsnet.co.jp/api/search", [
                'zipcode' => $postalCode,
            ]);
            
            // HTTPリクエストが失敗した場合
            if (!$response->successful()) {
                $errorMessage = '住所の取得に失敗しました。';
                if ($response->status() === 404) {
                    $errorMessage = '住所が見つかりませんでした。';
                }
                
                Log::error('郵便番号APIリクエスト失敗', [
                    'postal_code' => $postalCode,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                
                return response()->json([
                    'error' => $errorMessage,
                    'details' => config('app.debug') ? $response->body() : null,
                ], $response->status() === 404 ? 404 : 500);
            }
            
            $data = $response->json();
            
            // レスポンスデータの検証
            if (!is_array($data)) {
                Log::error('郵便番号APIレスポンス形式エラー', [
                    'postal_code' => $postalCode,
                    'data' => $data,
                ]);
                return response()->json(['error' => '住所の取得に失敗しました。'], 500);
            }
            
            // APIがエラーを返した場合
            if (isset($data['status']) && $data['status'] !== 200) {
                $message = $data['message'] ?? '住所が見つかりませんでした。';
                Log::warning('郵便番号APIエラーレスポンス', [
                    'postal_code' => $postalCode,
                    'status' => $data['status'],
                    'message' => $message,
                ]);
                return response()->json(['error' => $message], 404);
            }
            
            // 結果が存在する場合
            if (isset($data['results']) && is_array($data['results']) && count($data['results']) > 0) {
                $result = $data['results'][0];
                
                if (!isset($result['address1'])) {
                    Log::error('郵便番号APIレスポンスデータ不備', [
                        'postal_code' => $postalCode,
                        'result' => $result,
                    ]);
                    return response()->json(['error' => '住所の取得に失敗しました。'], 500);
                }
                
                $address = $result['address1'];
                if (isset($result['address2']) && !empty($result['address2'])) {
                    $address .= $result['address2'];
                }
                if (isset($result['address3']) && !empty($result['address3'])) {
                    $address .= $result['address3'];
                }
                
                return response()->json([
                    'address' => $address,
                ]);
            }
            
            // 結果が見つからない場合
            Log::info('郵便番号検索結果なし', ['postal_code' => $postalCode]);
            return response()->json(['error' => '住所が見つかりませんでした。'], 404);
            
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('郵便番号API接続エラー', [
                'postal_code' => $postalCode,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['error' => '住所検索サービスに接続できませんでした。しばらく時間をおいて再度お試しください。'], 500);
        } catch (\Exception $e) {
            Log::error('郵便番号APIエラー', [
                'postal_code' => $postalCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => '住所の取得に失敗しました。',
                'details' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}

