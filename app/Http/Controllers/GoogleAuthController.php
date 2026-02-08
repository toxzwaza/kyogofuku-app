<?php

namespace App\Http\Controllers;

use Google\Client as GoogleClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;

class GoogleAuthController extends Controller
{
    /**
     * Google OAuth 認証画面へリダイレクト
     */
    public function redirect(Request $request): RedirectResponse
    {
        $client = $this->createClient();

        return Redirect::away($client->createAuthUrl());
    }

    /**
     * Google OAuth コールバック - refresh_token を取得してプロフィールへ
     */
    public function callback(Request $request): RedirectResponse
    {
        $code = $request->query('code');
        if (empty($code)) {
            Log::warning('[GoogleAuth] コールバックに code がありません');
            return Redirect::route('profile.edit')->with('google_auth_error', '認証に失敗しました。もう一度お試しください。');
        }

        try {
            $client = $this->createClient();
            $token = $client->fetchAccessTokenWithAuthCode($code);

            if (isset($token['error'])) {
                Log::error('[GoogleAuth] トークン取得失敗', ['token' => $token]);
                return Redirect::route('profile.edit')->with('google_auth_error', 'トークンの取得に失敗しました: ' . ($token['error_description'] ?? $token['error']));
            }

            $refreshToken = $token['refresh_token'] ?? null;
            if (empty($refreshToken)) {
                Log::warning('[GoogleAuth] refresh_token がレスポンスに含まれていません', ['keys' => array_keys($token)]);
                return Redirect::route('profile.edit')->with('google_auth_error', 'refresh_token が取得できませんでした。Google アカウントのアクセス権限を一度削除してから、再度連携をお試しください。');
            }

            return Redirect::route('profile.edit')
                ->with('status', 'google_calendar_linked')
                ->with('google_calendar_refresh_token', $refreshToken);
        } catch (\Exception $e) {
            Log::error('[GoogleAuth] コールバック処理で例外', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            return Redirect::route('profile.edit')->with('google_auth_error', '認証処理中にエラーが発生しました: ' . $e->getMessage());
        }
    }

    protected function createClient(): GoogleClient
    {
        $client = new GoogleClient();
        $client->setClientId(config('services.google.client_id'));
        $client->setClientSecret(config('services.google.client_secret'));
        $client->setRedirectUri(config('services.google.redirect'));
        $client->setAccessType('offline');
        $client->setPrompt('consent'); // refresh_token 取得のため必須
        $client->setScopes([
            \Google\Service\Calendar::CALENDAR,
        ]);

        if (config('app.env') === 'local') {
            $client->setHttpClient(new \GuzzleHttp\Client(['verify' => false]));
        }

        return $client;
    }
}
