<?php

namespace App\Services\Referral;

use App\Models\Customer;
use App\Models\GiftCard;
use App\Models\PointLedger;
use App\Models\Referral;

/**
 * 顧客詳細「ポイント・ギフト」タブ向けのサマリ生成。
 */
class ReferralSummaryService
{
    public function forCustomer(Customer $customer): array
    {
        $stage = $customer->stage; // CustomerStage|null
        $balance = (int) (optional($customer->referralPoint)->balance ?? 0);

        $ledger = PointLedger::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (PointLedger $l) => [
                'id' => $l->id,
                'amount' => $l->amount,
                'type' => $l->type,
                'note' => $l->note,
                'created_at' => $l->created_at?->format('Y-m-d H:i'),
            ]);

        $giftCards = GiftCard::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (GiftCard $g) => [
                'id' => $g->id,
                'amount' => $g->amount,
                'status' => $g->status,
                'issued_at' => $g->issued_at?->format('Y-m-d H:i'),
                'canceled_at' => $g->canceled_at?->format('Y-m-d H:i'),
                'cancelable' => $g->isCancelable(),
            ]);

        // 自分が紹介者の紹介関係（ステータス別件数）
        $byStatus = Referral::query()
            ->where('referrer_customer_id', $customer->id)
            ->selectRaw('status, count(*) as cnt')
            ->groupBy('status')
            ->pluck('cnt', 'status');

        return [
            'stage' => $stage?->stage ?? 'bronze',
            'matured_referrals_count' => (int) ($stage?->matured_referrals_count ?? 0),
            'referral_code' => optional($customer->referralCode)->code,
            'balance' => $balance,
            'gift_card_unit' => \App\Models\ReferralSetting::getInt('gift_card_unit', 500),
            'ledger' => $ledger,
            'gift_cards' => $giftCards,
            'referrals_made' => [
                'linked' => (int) ($byStatus['linked'] ?? 0),
                'contracted' => (int) ($byStatus['contracted'] ?? 0),
                'matured' => (int) ($byStatus['matured'] ?? 0),
                'expired' => (int) ($byStatus['expired'] ?? 0),
                'rejected' => (int) ($byStatus['rejected'] ?? 0),
            ],
        ];
    }
}
