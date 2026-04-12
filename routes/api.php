<?php

use App\Http\Controllers\Api\NaturalLanguageController;
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

// 自然言語 API（Claude API tool_use で操作。Bearer トークン認証）
Route::post('/nl/chat', [NaturalLanguageController::class, 'chat']);
