<?php

use App\Http\Controllers\GoogleCalendarKeepTokenController;
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
