<?php

namespace App\Services\Referral;

use App\Models\Customer;
use App\Models\GiftCard;
use App\Models\PointLedger;
use App\Models\ReferralSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * ギフトカード引換。発行は店舗対応で、システムは「発行済み」記録とポイント減算を行う。
 * 誤発行はキャンセルでポイント返還。
 */
class GiftCardService
{
    public function __construct(private ReferralPointService $points)
    {
    }

    /**
     * ギフトカードを発行（＝ポイント減算）。
     * 交換レート（gift_card_rate＝1pt=◯円分）に従い、必要ポイント = 円 ÷ レート（端数切上げ）を消費する。
     *
     * @param  int  $amount  引換額（円）。引換単位の倍数・残高以内。
     */
    public function issue(Customer $customer, int $amount, ?int $userId = null, ?int $shopId = null): GiftCard
    {
        $unit = max(1, ReferralSetting::getInt('gift_card_unit', 500));

        if ($amount <= 0 || $amount % $unit !== 0) {
            throw ValidationException::withMessages([
                'amount' => "引換額は{$unit}円単位で指定してください。",
            ]);
        }

        // 交換レート：必要ポイント = 交換額(円) ÷ レート（端数は切上げ）
        $rate = ReferralSetting::getFloat('gift_card_rate', 0.8);
        $rate = $rate > 0 ? $rate : 1.0;
        $pointsNeeded = (int) ceil($amount / $rate);

        if ($this->points->balanceOf($customer) < $pointsNeeded) {
            throw ValidationException::withMessages([
                'amount' => 'ポイント残高が不足しています。',
            ]);
        }

        return DB::transaction(function () use ($customer, $amount, $pointsNeeded, $userId, $shopId) {
            $giftCard = GiftCard::create([
                'customer_id' => $customer->id,
                'amount' => $amount,
                'points_spent' => $pointsNeeded,
                'status' => GiftCard::STATUS_ISSUED,
                'issued_by_user_id' => $userId,
                'issued_shop_id' => $shopId,
                'issued_at' => now(),
            ]);

            $this->points->applyDelta(
                $customer,
                -$pointsNeeded,
                PointLedger::TYPE_GIFT_CARD_REDEEM,
                ['gift_card_id' => $giftCard->id, 'note' => "ギフトカード発行（{$amount}円分・{$pointsNeeded}pt）"],
            );

            return $giftCard;
        });
    }

    /**
     * 未取消のギフトカードをキャンセル（＝ポイント返還）。
     */
    public function cancel(GiftCard $giftCard): GiftCard
    {
        if (!$giftCard->isCancelable()) {
            throw ValidationException::withMessages([
                'gift_card' => 'このギフトカードはキャンセルできません。',
            ]);
        }

        // 発行時に消費したポイントを返還する（旧データ＝points_spent 未記録の場合は amount=円をそのまま返還＝1pt=1円時代）
        $refund = $giftCard->points_spent ?? $giftCard->amount;

        return DB::transaction(function () use ($giftCard, $refund) {
            $giftCard->update([
                'status' => GiftCard::STATUS_CANCELED,
                'canceled_at' => now(),
            ]);

            $this->points->applyDelta(
                $giftCard->customer,
                $refund,
                PointLedger::TYPE_GIFT_CARD_CANCEL_REFUND,
                ['gift_card_id' => $giftCard->id, 'note' => "ギフトカード取消（{$refund}pt返還）"],
            );

            return $giftCard->fresh();
        });
    }
}
