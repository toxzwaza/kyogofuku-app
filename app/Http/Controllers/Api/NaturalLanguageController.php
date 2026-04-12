<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\NaturalLanguage\NlChatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class NaturalLanguageController extends Controller
{
    /**
     * POST /api/nl/chat
     *
     * 自然言語でシステムを操作する。
     *
     * Request body:
     *   message  (string, required) — 自然言語の指示
     *   confirm  (bool, optional)   — true にすると書き込み操作を確認なしで実行
     *
     * Headers:
     *   Authorization: Bearer {NL_API_SECRET}
     */
    public function chat(Request $request): JsonResponse
    {
        // API トークン認証
        $token = $request->bearerToken();
        $secret = config('services.nl_api.secret');

        if (empty($secret) || $token !== $secret) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'message' => 'required|string|max:2000',
            'confirm' => 'boolean',
        ]);

        $message = $validated['message'];
        $confirmed = $validated['confirm'] ?? false;

        Log::info('[NL API] リクエスト受信', [
            'message' => $message,
            'confirm' => $confirmed,
            'ip' => $request->ip(),
        ]);

        try {
            $service = new NlChatService();
            $result = $service->chat($message, $confirmed);

            Log::info('[NL API] レスポンス', [
                'actions_count' => count($result['actions']),
                'message_length' => mb_strlen($result['message']),
            ]);

            return response()->json($result);
        } catch (\Throwable $e) {
            Log::error('[NL API] エラー', [
                'error' => $e->getMessage(),
                'message' => $message,
            ]);

            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
