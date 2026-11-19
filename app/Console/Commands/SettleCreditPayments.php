<?php

namespace App\Console\Commands;

use App\Models\CreditContract;
use Illuminate\Console\Command;

/** 信販会社の分の過ぎた月を支払済にし、払い終わった契約を完済にする */
class SettleCreditPayments extends Command
{
    protected $signature = 'credit:settle';

    protected $description = '信販会社のクレジットで、今月より前の月を自動で支払済にする（払い終わったら完済）';

    public function handle(): int
    {
        $count = CreditContract::settleAgencyPayments();
        $this->info("支払済にした件数: {$count}");

        return self::SUCCESS;
    }
}
