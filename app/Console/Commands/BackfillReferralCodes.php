<?php

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\ReferralCode;
use Illuminate\Console\Command;

/**
 * 既存の「成約済」顧客に紹介コードを一括発行する（未発行のみ）。
 * 紹介できるのは成約済顧客のみ（§1.2 A）のため、対象を成約済に限定する。
 */
class BackfillReferralCodes extends Command
{
    protected $signature = 'referral:backfill-codes';

    protected $description = '成約済顧客に紹介コードを一括発行（未発行のみ）';

    public function handle(): int
    {
        $issued = 0;

        Customer::query()
            ->whereHas('contracts', fn ($q) => $q->where('status', '確定'))
            ->whereDoesntHave('referralCode')
            ->chunkById(200, function ($customers) use (&$issued) {
                foreach ($customers as $customer) {
                    ReferralCode::create([
                        'customer_id' => $customer->id,
                        'code' => ReferralCode::generateUniqueCode(),
                    ]);
                    $issued++;
                }
            });

        $this->info("紹介コード発行: {$issued} 件");

        return self::SUCCESS;
    }
}
