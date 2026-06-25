<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\ResolvesUiView;
use App\Http\Controllers\Controller;
use App\Models\Coupon;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CouponController extends Controller
{
    use ResolvesUiView;

    public function index()
    {
        $coupons = Coupon::query()
            ->withCount('customerCoupons')
            ->orderByDesc('id')
            ->paginate(20)
            ->through(fn (Coupon $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'discount_type' => $c->discount_type,
                'discount_value' => $c->discount_value,
                'combinable' => $c->combinable,
                'status' => $c->status,
                'thumbnail_url' => $c->thumbnail_url,
                'valid_days' => $c->valid_days,
                'valid_until_fixed' => $c->valid_until_fixed?->format('Y-m-d'),
                'distributed' => $c->customer_coupons_count,
            ]);

        return Inertia::render($this->viewFor('Admin/Coupon/Index'), [
            'coupons' => $coupons,
        ]);
    }

    public function store(Request $request)
    {
        $coupon = Coupon::create($this->validated($request));

        return redirect()->route('admin.coupons.index')->with('success', "クーポン「{$coupon->name}」を作成しました。");
    }

    public function update(Request $request, Coupon $coupon)
    {
        $coupon->update($this->validated($request));

        return redirect()->route('admin.coupons.index')->with('success', 'クーポンを更新しました。');
    }

    public function destroy(Coupon $coupon)
    {
        if ($coupon->customerCoupons()->exists()) {
            // 配布実績がある場合は archived にして残す（履歴保持）
            $coupon->update(['status' => Coupon::STATUS_ARCHIVED]);

            return redirect()->route('admin.coupons.index')->with('info', '配布実績があるためアーカイブしました。');
        }

        $coupon->delete();

        return redirect()->route('admin.coupons.index')->with('success', 'クーポンを削除しました。');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:100',
            'description' => 'nullable|string',
            'thumbnail_path' => 'nullable|string|max:1000',
            'thumbnail_disk' => 'nullable|string|max:20',
            'terms_text' => 'nullable|string',
            'discount_type' => 'required|in:fixed,rate',
            'discount_value' => 'required|integer|min:0',
            'valid_days' => 'nullable|integer|min:1',
            'valid_until_fixed' => 'nullable|date',
            'combinable' => 'boolean',
            'status' => 'required|in:active,inactive,archived',
        ]);
    }
}
