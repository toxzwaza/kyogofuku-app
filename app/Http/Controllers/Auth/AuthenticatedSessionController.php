<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\ActivityLog;
use App\Models\BlockedIp;
use App\Providers\RouteServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(Request $request): Response
    {
        // blocked_ipsテーブルでブロックされているかチェック
        $ipAddress = $request->ip();
        $blockedIp = BlockedIp::where('ip_address', $ipAddress)
            ->where('is_active', true)
            ->first();

        if ($blockedIp) {
            return Inertia::render('Auth/Login', [
                'canResetPassword' => Route::has('password.request'),
                'status' => session('status'),
                'blocked' => true,
                'failureCount' => $blockedIp->failure_count,
                'ipAddress' => $ipAddress,
            ]);
        }

        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
            'blocked' => false,
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        try {
            $request->authenticate();
        } catch (ValidationException $e) {
            // ログイン失敗を記録（ValidationExceptionがスローされた場合）
            // LoginRequestで既に記録されている可能性があるが、念のためここでも記録
            try {
                $login = $request->input('login');
                // 既に記録されているかチェック（重複を避ける）
                $existingLog = ActivityLog::where('ip_address', $request->ip())
                    ->where('action_type', 'login_failed')
                    ->where('description', 'like', '%ログイン失敗: ' . $login . '%')
                    ->where('created_at', '>=', now()->subMinute())
                    ->first();
                
                if (!$existingLog) {
                    // 不正アクセス検知のため、パスワードはそのまま記録
                    $password = $request->input('password');
                    
                    ActivityLog::create([
                        'user_id' => null,
                        'shop_id' => null,
                        'action_type' => 'login_failed',
                        'resource_type' => null,
                        'resource_id' => null,
                        'route_name' => 'login',
                        'url' => $request->fullUrl(),
                        'method' => $request->method(),
                        'description' => 'ログイン失敗: ' . $login,
                        'old_values' => null,
                        'new_values' => [
                            'login' => $login,
                            'password' => $password,
                        ],
                        'ip_address' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                    ]);
                }

                // ログイン失敗が1日で10回以上の場合、blocked_ipsテーブルに登録
                $ipAddress = $request->ip();
                $failureCount = ActivityLog::where('ip_address', $ipAddress)
                    ->where('action_type', 'login_failed')
                    ->whereDate('created_at', today())
                    ->count();

                if ($failureCount >= 10) {
                    // 既にブロックされているかチェック
                    $blockedIp = BlockedIp::where('ip_address', $ipAddress)
                        ->where('is_active', true)
                        ->first();

                    if (!$blockedIp) {
                        // 最初の失敗日時と最後の失敗日時を取得
                        $firstFailed = ActivityLog::where('ip_address', $ipAddress)
                            ->where('action_type', 'login_failed')
                            ->whereDate('created_at', today())
                            ->orderBy('created_at', 'asc')
                            ->first();
                        $lastFailed = ActivityLog::where('ip_address', $ipAddress)
                            ->where('action_type', 'login_failed')
                            ->whereDate('created_at', today())
                            ->orderBy('created_at', 'desc')
                            ->first();

                        BlockedIp::create([
                            'ip_address' => $ipAddress,
                            'failure_count' => $failureCount,
                            'first_failed_at' => $firstFailed ? $firstFailed->created_at : now(),
                            'last_failed_at' => $lastFailed ? $lastFailed->created_at : now(),
                            'blocked_at' => now(),
                            'is_active' => true,
                        ]);
                    } else {
                        // 既にブロックされている場合は失敗回数と最終失敗日時を更新
                        $lastFailed = ActivityLog::where('ip_address', $ipAddress)
                            ->where('action_type', 'login_failed')
                            ->whereDate('created_at', today())
                            ->orderBy('created_at', 'desc')
                            ->first();

                        $blockedIp->update([
                            'failure_count' => $failureCount,
                            'last_failed_at' => $lastFailed ? $lastFailed->created_at : now(),
                        ]);
                    }
                }
            } catch (\Exception $logException) {
                // ログ記録に失敗しても処理は続行
                \Illuminate\Support\Facades\Log::error('Failed to log login failure in controller', [
                    'error' => $logException->getMessage(),
                    'login' => $request->input('login'),
                    'ip' => $request->ip(),
                ]);
            }
            
            throw $e;
        }

        $request->session()->regenerate();

        // ログインログを記録
        $ipAddress = $request->ip();
        $user = Auth::user();
        $shopId = null;
        try {
            /** @var \App\Models\User $user */
            $shop = $user->shops()->first();
            $shopId = $shop?->id;
        } catch (\Exception $e) {
            // リレーションが存在しない場合はnullのまま
        }

        ActivityLog::create([
            'user_id' => $user->id,
            'shop_id' => $shopId,
            'action_type' => 'login',
            'resource_type' => null,
            'resource_id' => null,
            'route_name' => 'login',
            'url' => $request->fullUrl(),
            'method' => $request->method(),
            'description' => 'ログイン',
            'old_values' => null,
            'new_values' => null,
            'ip_address' => $ipAddress,
            'user_agent' => $request->userAgent(),
        ]);

        return redirect()->intended(RouteServiceProvider::HOME);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        // ログアウト前にユーザー情報を取得
        $user = Auth::user();
        $shopId = null;
        if ($user) {
            try {
                /** @var \App\Models\User $user */
                $shop = $user->shops()->first();
                $shopId = $shop?->id;
            } catch (\Exception $e) {
                // リレーションが存在しない場合はnullのまま
            }

            // ログアウトログを記録
            ActivityLog::create([
                'user_id' => $user->id,
                'shop_id' => $shopId,
                'action_type' => 'logout',
                'resource_type' => null,
                'resource_id' => null,
                'route_name' => 'logout',
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'description' => 'ログアウト',
                'old_values' => null,
                'new_values' => null,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
