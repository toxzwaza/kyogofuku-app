<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\DeviceRegistration;
use App\Models\Shop;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * 端末登録（ログイン画面・guest からアクセス）
 *
 * - status : localStorage の端末トークンが有効かを返す（パスワード欄の表示制御用）
 * - register : 店舗パスワードを検証し、端末を登録して生トークンを返す（client が localStorage 保存）
 */
class DeviceController extends Controller
{
    /**
     * 端末トークンの有効性確認
     */
    public function status(Request $request): JsonResponse
    {
        $token = (string) ($request->input('device_token') ?? '');
        $device = DeviceRegistration::findActiveByToken($token);

        if (! $device) {
            return response()->json(['registered' => false], 200, [], JSON_UNESCAPED_UNICODE);
        }

        $device->forceFill([
            'last_used_at' => now(),
            'last_ip' => $request->ip(),
        ])->save();

        return response()->json([
            'registered' => true,
            'device_code' => $device->device_code,
            'shop_id' => $device->shop_id,
            'shop_name' => $device->shop?->name,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    /**
     * 端末登録（店舗パスワード検証 → トークン発行）
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'shop_id' => ['required', 'integer', 'exists:shops,id'],
            'password' => ['required', 'string'],
            'label' => ['nullable', 'string', 'max:100'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        $shop = Shop::find($validated['shop_id']);

        if (! $shop || empty($shop->device_password)) {
            return response()->json([
                'success' => false,
                'message' => 'この店舗は端末登録が未設定です。管理者にお問い合わせください。',
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

        if (! Hash::check($validated['password'], $shop->device_password)) {
            $this->log('device_register_failed', null, $shop->id, $request, '端末登録失敗（パスワード不一致）: shop_id='.$shop->id);

            return response()->json([
                'success' => false,
                'message' => '店舗パスワードが正しくありません。',
            ], 422, [], JSON_UNESCAPED_UNICODE);
        }

        $token = Str::random(64);
        $device = DeviceRegistration::create([
            'shop_id' => $shop->id,
            'device_code' => DeviceRegistration::generateUniqueDeviceCode(),
            'token_hash' => DeviceRegistration::hashToken($token),
            'label' => $validated['label'] ?? null,
            'ip_address' => $request->ip(),
            'last_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'registered_by_user_id' => $validated['user_id'] ?? null,
            'last_used_at' => now(),
        ]);

        $this->log('device_registered', $validated['user_id'] ?? null, $shop->id, $request, '端末登録: '.$device->device_code.' / shop_id='.$shop->id);

        return response()->json([
            'success' => true,
            'device_token' => $token,
            'device_code' => $device->device_code,
            'shop_name' => $shop->name,
        ], 200, [], JSON_UNESCAPED_UNICODE);
    }

    private function log(string $action, ?int $userId, ?int $shopId, Request $request, string $description): void
    {
        try {
            ActivityLog::create([
                'user_id' => $userId,
                'shop_id' => $shopId,
                'action_type' => $action,
                'route_name' => $request->route()?->getName(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'description' => $description,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        } catch (\Throwable $e) {
            // ログ失敗は本処理に影響させない
        }
    }
}
