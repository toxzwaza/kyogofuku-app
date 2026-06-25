<?php

namespace App\Console\Commands;

use App\Models\Referral;
use Illuminate\Console\Command;

/**
 * 成立しないまま有効期限を過ぎた紹介（linked）を expired にする。
 */
class ExpireReferrals extends Command
{
    protected $signature = 'referral:expire';

    protected $description = '有効期限切れの未成立紹介を expired 化する';

    public function handle(): int
    {
        $count = Referral::query()
            ->where('status', Referral::STATUS_LINKED)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now())
            ->update(['status' => Referral::STATUS_EXPIRED]);

        $this->info("expired: {$count} 件");

        return self::SUCCESS;
    }
}
