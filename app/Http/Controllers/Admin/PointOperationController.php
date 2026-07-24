<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\PointLedger;
use App\Services\Referral\PointTransferService;
use App\Services\Referral\ReferralPointService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * 紹介ポイントの「使用」操作（管理画面）。
 * - 社内商品購入での使用（1pt=1円）
 * - 顧客間のポイント譲渡
 */
class PointOperationController extends Controller
{
    public function __construct(
        private ReferralPointService $points,
        private PointTransferService $transfers,
    ) {
    }

    /**
     * 社内商品購入での使用（1pt=1円）。
     */
    public function purchase(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'amount' => 'required|integer|min:1',
            'note' => 'nullable|string|max:255',
        ]);
        $amount = (int) $validated['amount'];

        if ($this->points->totalBalanceOf($customer) < $amount) {
            return back()->withErrors(['amount' => 'ポイント残高が不足しています。']);
        }

        $note = '商品購入での使用'.(!empty($validated['note']) ? "（{$validated['note']}）" : '');
        // 平田ポイントを優先して消費（不足分は紹介ポイント）
        $this->points->spendPreferHirata($customer, $amount, PointLedger::TYPE_PRODUCT_PURCHASE, $note);

        return back()->with('success', "{$amount}ポイントを商品購入に使用しました。");
    }

    /**
     * ポイント譲渡（顧客A→顧客B）。
     */
    public function transfer(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'to_customer_id' => 'required|integer|exists:customers,id',
            'points' => 'required|integer|min:1',
            'note' => 'nullable|string|max:255',
        ]);

        $to = Customer::findOrFail((int) $validated['to_customer_id']);

        try {
            $this->transfers->transfer($customer, $to, (int) $validated['points'], $validated['note'] ?? null);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', "{$validated['points']}ポイントを「{$to->name}」様へ譲渡しました。");
    }
}
