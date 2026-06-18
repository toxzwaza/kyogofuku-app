<?php

use App\Http\Controllers\Api\AttendanceMasterImportController;
use App\Http\Controllers\Api\NaturalLanguageController;
use App\Http\Controllers\Api\PublicEventController;
use App\Http\Controllers\GoogleCalendarKeepTokenController;
use App\Http\Controllers\UtmAnalyticsApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Google Calendar refresh トークン維持用（Python/cron から週1回アクセス。X-Api-Key または token で認証）
Route::get('/google-calendar/keep-token', GoogleCalendarKeepTokenController::class);

// UTM 流入経路分析 API（GAS 等から計測・分析用。X-Api-Key または token で認証）
Route::get('/utm-analytics', UtmAnalyticsApiController::class);

// 勤怠マスタ（勤務属性・会社カレンダー）取込API（X-Api-Key または token で認証）
Route::get('/attendance/master-import', [AttendanceMasterImportController::class, 'current']);
Route::post('/attendance/master-import', [AttendanceMasterImportController::class, 'import']);

// 自然言語 API（Claude API tool_use で操作。Bearer トークン認証）
Route::post('/nl/chat', [NaturalLanguageController::class, 'chat']);

// MCP サーバー用 tool 直接実行エンドポイント（Bearer トークン認証）
Route::post('/tools/execute', [NaturalLanguageController::class, 'executeTool']);

// 外部WP（kouichi/hirata）連携用 公開API（認証なし、CORS で許可ドメインのみ）
Route::prefix('public')->group(function () {
    Route::get('/events', [PublicEventController::class, 'index']);
    Route::get('/events/footer-banner', [PublicEventController::class, 'footerBanner']);
});
