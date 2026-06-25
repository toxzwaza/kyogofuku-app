<?php

namespace App\Observers;

use App\Models\Contract;
use App\Models\Referral;
use App\Models\ReferralCode;

class ContractObserver
{
    public function created(Contract $contract): void
    {
        $this->markContractedIfConfirmed($contract);
    }

    public function updated(Contract $contract): void
    {
        $this->markContractedIfConfirmed($contract);
    }

    /**
     * 被紹介者が成約（status='確定'）したら、対象の紹介を「contracted（仮確定）」にする。
     * 特典付与・ステージ評価は行わない（成約1ヶ月後の referral:mature バッチで確定）。
     * idempotent：既に contracted/matured のものは触らない。
     */
    private function markContractedIfConfirmed(Contract $contract): void
    {
        if (($contract->status ?? null) !== '確定') {
            return;
        }
        if (!$contract->customer_id) {
            return;
        }

        // 成約済顧客は紹介可能になるため、紹介コードを発行（未発行なら）。
        ReferralCode::firstOrCreate(
            ['customer_id' => $contract->customer_id],
            ['code' => ReferralCode::generateUniqueCode()],
        );

        Referral::query()
            ->where('referred_customer_id', $contract->customer_id)
            ->where('status', Referral::STATUS_LINKED)
            ->get()
            ->each(function (Referral $referral) use ($contract) {
                $referral->update([
                    'status' => Referral::STATUS_CONTRACTED,
                    'contract_id' => $contract->id,
                    'contracted_at' => now(),
                ]);
            });
    }
}
