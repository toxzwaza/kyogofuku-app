<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\GiftCard;
use App\Services\Referral\GiftCardService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class GiftCardController extends Controller
{
    public function __construct(private GiftCardService $giftCards)
    {
    }

    /**
     * ギフトカードを「発行済み」記録（＝ポイント減算）。
     */
    public function issue(Request $request, Customer $customer)
    {
        $validated = $request->validate([
            'amount' => 'required|integer|min:1',
        ]);

        try {
            $this->giftCards->issue(
                $customer,
                (int) $validated['amount'],
                $request->user()?->id,
                $request->user()?->shops()->value('shops.id'),
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'ギフトカードを発行しました（ポイントを減算しました）。');
    }

    /**
     * ギフトカードをキャンセル（＝ポイント返還）。
     */
    public function cancel(GiftCard $giftCard)
    {
        try {
            $this->giftCards->cancel($giftCard);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        return back()->with('success', 'ギフトカードをキャンセルしました（ポイントを返還しました）。');
    }
}
