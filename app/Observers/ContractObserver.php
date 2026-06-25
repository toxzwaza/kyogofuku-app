<?php

namespace App\Observers;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\ReferralCode;
use App\Services\Referral\ReferralCustomerSyncService;

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

        // 被紹介者としての紹介を同期（補完・最初の紹介者のみ有効・contracted昇格）。
        $customer = Customer::find($contract->customer_id);
        if ($customer) {
            app(ReferralCustomerSyncService::class)->sync($customer);
        }
    }
}
