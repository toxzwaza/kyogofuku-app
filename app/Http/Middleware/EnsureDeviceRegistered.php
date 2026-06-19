<?php

namespace App\Http\Middleware;

use App\Models\DeviceRegistration;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * 端末ゲート：ログイン中ユーザーのセッションに紐づく登録端末が有効か毎リクエスト確認する。
 * 端末が解除（revoke）されていたら即ログアウトしてログイン画面へ戻す（解除の即時反映）。
 *
 * config('auth.device_gate_enabled') が false の間は何もしない（段階導入）。
 */
class EnsureDeviceRegistered
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('auth.device_gate_enabled')) {
            return $next($request);
        }

        // 未ログイン（guest）は対象外。ログアウト操作はそのまま通す。
        if (! Auth::check() || $request->routeIs('logout')) {
            return $next($request);
        }

        $deviceId = $request->session()->get('device_registration_id');
        $device = $deviceId ? DeviceRegistration::find($deviceId) : null;

        if (! $device || ! $device->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', '端末の登録が解除されました。再度ログインしてください。');
        }

        return $next($request);
    }
}
