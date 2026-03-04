<?php

namespace App\Http\Controllers;

use App\Services\GoogleCalendarSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoogleCalendarKeepTokenController extends Controller
{
    /**
     * Google Calendar refresh トークンを「使用」して6ヶ月未使用による失効を防ぐ
     * 認証: X-Api-Key ヘッダー または token クエリパラメータで GOOGLE_CALENDAR_KEEP_TOKEN_SECRET を送信
     */
    public function __invoke(Request $request, GoogleCalendarSyncService $syncService): JsonResponse
    {
        $secret = config('services.google.calendar_keep_token_secret');
        if (empty($secret)) {
            return response()->json(['success' => false, 'message' => 'エンドポイントが未設定です'], 503, [], JSON_UNESCAPED_UNICODE);
        }

        $token = $request->header('X-Api-Key') ?? $request->query('token');
        if ($token !== $secret) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401, [], JSON_UNESCAPED_UNICODE);
        }

        $result = $syncService->keepRefreshTokenAlive();

        return response()->json([
            'success' => $result,
            'message' => $result ? 'トークン維持処理が成功しました' : 'トークン未設定のためスキップしました',
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }
}
