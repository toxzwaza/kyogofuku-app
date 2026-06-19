<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesUiView;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\DeviceRegistration;
use App\Models\Shop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class DeviceRegistrationController extends Controller
{
    use ResolvesUiView;

    private function ensureAttendanceManager(Request $request): void
    {
        if (! $request->user()->isAttendanceManager()) {
            abort(403);
        }
    }

    public function index(Request $request)
    {
        $this->ensureAttendanceManager($request);

        $devices = DeviceRegistration::query()
            ->with(['shop:id,name', 'registeredBy:id,name', 'revokedBy:id,name'])
            ->orderByRaw('revoked_at is null desc')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (DeviceRegistration $d) => [
                'id' => $d->id,
                'device_code' => $d->device_code,
                'shop' => $d->shop ? ['id' => $d->shop->id, 'name' => $d->shop->name] : null,
                'label' => $d->label,
                'ip_address' => $d->ip_address,
                'last_ip' => $d->last_ip,
                'user_agent' => $d->user_agent,
                'registered_by' => $d->registeredBy?->name,
                'last_used_at' => $d->last_used_at?->toIso8601String(),
                'created_at' => $d->created_at?->toIso8601String(),
                'revoked_at' => $d->revoked_at?->toIso8601String(),
                'revoked_by' => $d->revokedBy?->name,
                'active' => $d->isActive(),
            ])
            ->values()
            ->all();

        $shops = Shop::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'device_password', 'device_password_updated_at'])
            ->map(fn (Shop $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'has_password' => ! empty($s->device_password),
                'password_updated_at' => $s->device_password_updated_at?->toIso8601String(),
                'active_device_count' => $s->deviceRegistrations()->whereNull('revoked_at')->count(),
            ])
            ->values()
            ->all();

        return Inertia::render($this->viewFor('Admin/DeviceRegistration/Index'), [
            'devices' => $devices,
            'shops' => $shops,
            'deviceGateEnabled' => (bool) config('auth.device_gate_enabled'),
        ]);
    }

    public function revoke(Request $request, DeviceRegistration $device)
    {
        $this->ensureAttendanceManager($request);

        if ($device->isActive()) {
            $device->update([
                'revoked_at' => now(),
                'revoked_by_user_id' => $request->user()->id,
            ]);
            $this->log($request, 'device_revoked', $device->shop_id, '端末解除: '.$device->device_code);
        }

        return back()->with('success', '端末「'.$device->device_code.'」を解除しました。');
    }

    public function updateShopPassword(Request $request, Shop $shop)
    {
        $this->ensureAttendanceManager($request);

        $validated = $request->validate([
            'password' => ['required', 'string', 'min:4', 'max:100'],
        ]);

        $shop->update([
            'device_password' => Hash::make($validated['password']),
            'device_password_updated_at' => now(),
        ]);
        $this->log($request, 'shop_device_password_updated', $shop->id, '店舗端末パスワード設定/変更: shop_id='.$shop->id);

        return back()->with('success', $shop->name.' の端末登録パスワードを更新しました。');
    }

    private function log(Request $request, string $action, ?int $shopId, string $description): void
    {
        try {
            ActivityLog::create([
                'user_id' => $request->user()?->id,
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
