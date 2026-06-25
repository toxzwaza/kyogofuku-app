<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\CustomerCoupon;
use Illuminate\Http\Request;

class CustomerCouponController extends Controller
{
    /**
     * 顧客へクーポンを配布（held で付与）。有効期限を確定する。
     */
    public function distribute(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'coupon_id' => 'required|exists:coupons,id',
        ]);

        $coupon = Coupon::findOrFail($validated['coupon_id']);
        if ($coupon->status !== Coupon::STATUS_ACTIVE) {
            return back()->withErrors(['coupon_id' => '有効なクーポンではありません。']);
        }

        $validUntil = $coupon->valid_until_fixed
            ?: ($coupon->valid_days ? now()->addDays($coupon->valid_days)->toDateString() : null);

        CustomerCoupon::create([
            'customer_id' => $customer->id,
            'coupon_id' => $coupon->id,
            'status' => CustomerCoupon::STATUS_HELD,
            'valid_until' => $validUntil,
        ]);

        return back()->with('success', "クーポン「{$coupon->name}」を配布しました。");
    }

    /**
     * クーポンを使用済みにする（管理画面の使用ボタン）。
     */
    public function markUsed(Request $request, CustomerCoupon $customerCoupon)
    {
        if (!$customerCoupon->isUsable()) {
            return back()->withErrors(['coupon' => 'このクーポンは使用できません。']);
        }

        $customerCoupon->update([
            'status' => CustomerCoupon::STATUS_USED,
            'used_at' => now(),
            'used_by_user_id' => $request->user()?->id,
            'used_shop_id' => $request->user()?->shops()->value('shops.id'),
        ]);

        return back()->with('success', 'クーポンを使用済みにしました。');
    }
}
