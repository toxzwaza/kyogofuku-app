<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class GoogleAuthController extends Controller
{
    /**
     * Google OAuth リダイレクト
     */
    public function redirect(): RedirectResponse
    {
        Log::info('[GoogleCalendar] OAuth redirect 開始', [
            'user_id' => Auth::id(),
            'client_id_set' => !empty(config('services.google.client_id')),
            'client_secret_set' => !empty(config('services.google.client_secret')),
            'redirect_uri' => config('services.google.redirect'),
        ]);

        try {
            $response = Socialite::driver('google')
                ->scopes([
                    'https://www.googleapis.com/auth/calendar',
                    'https://www.googleapis.com/auth/calendar.events',
                ])
                ->with(['access_type' => 'offline', 'prompt' => 'consent'])
                ->redirect();
            Log::info('[GoogleCalendar] OAuth redirect 成功、Google へリダイレクト');
            return $response;
        } catch (\Throwable $e) {
            Log::error('[GoogleCalendar] OAuth redirect 失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Google OAuth コールバック
     */
    public function callback(Request $request): RedirectResponse
    {
        Log::info('[GoogleCalendar] OAuth callback 受信', ['has_code' => $request->has('code'), 'has_error' => $request->has('error')]);

        if ($request->has('error')) {
            Log::warning('[GoogleCalendar] OAuth エラー', ['error' => $request->get('error')]);
            return redirect()->route('profile.edit')
                ->with('error', 'Google アカウントとの連携に失敗しました: ' . $request->get('error'));
        }

        try {
            $googleUser = Socialite::driver('google')->user();
            Log::info('[GoogleCalendar] OAuth ユーザー取得成功', [
                'google_id' => $googleUser->getId(),
                'has_refresh_token' => !empty($googleUser->refreshToken),
            ]);
        } catch (\Throwable $e) {
            Log::error('[GoogleCalendar] OAuth callback 失敗', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return redirect()->route('profile.edit')
                ->with('error', 'Google アカウントとの連携に失敗しました。もう一度お試しください。');
        }

        $user = Auth::user();
        if (!$user) {
            Log::warning('[GoogleCalendar] 未ログイン状態で callback 受信');
            return redirect()->route('login');
        }

        $refreshToken = $googleUser->refreshToken ?? $user->google_calendar_refresh_token;
        $user->update([
            'google_calendar_refresh_token' => $refreshToken,
            'google_calendar_token_expires_at' => $googleUser->expiresIn
                ? now()->addSeconds($googleUser->expiresIn)
                : null,
        ]);

        Log::info('[GoogleCalendar] 連携完了', [
            'user_id' => $user->id,
            'refresh_token_saved' => !empty($refreshToken),
        ]);

        return redirect()->route('profile.edit')
            ->with('status', 'Google カレンダーと連携しました。');
    }

    /**
     * Google カレンダー連携を解除
     */
    public function disconnect(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->update([
            'google_calendar_refresh_token' => null,
            'google_calendar_token_expires_at' => null,
        ]);

        return redirect()->route('profile.edit')
            ->with('status', 'Google カレンダーとの連携を解除しました。');
    }
}
