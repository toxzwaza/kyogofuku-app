<?php

namespace App\Services\Referral;

use App\Models\Customer;
use App\Models\Referral;
use Illuminate\Support\Facades\DB;

/**
 * 被紹介者（顧客）の状況に応じて紹介レコードを同期する（冪等）。
 *
 *  1. referred_customer_id の補完：LINE連携が顧客作成より後に行われたケースに対応
 *     （referred_line_user_id 一致の紹介に customer を結び付ける）
 *  2. 「最初の紹介者のみ有効」：有効な紹介は最古の1件のみ。他の linked は
 *     reject_reason='already_referred' で却下する
 *  3. 有効な紹介が linked で、被紹介者に確定成約があれば contracted（仮）へ昇格
 *
 * 呼び出し箇所：顧客作成時 / LINE連携時 / 成約確定時。
 */
class ReferralCustomerSyncService
{
    public function sync(Customer $customer): void
    {
        $lineUserIds = $customer->lineContacts()
            ->pluck('line_user_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $referrals = Referral::query()
            ->where(function ($q) use ($customer, $lineUserIds) {
                $q->where('referred_customer_id', $customer->id);
                if ($lineUserIds) {
                    $q->orWhereIn('referred_line_user_id', $lineUserIds);
                }
            })
            ->orderBy('id')
            ->get();

        if ($referrals->isEmpty()) {
            return;
        }

        DB::transaction(function () use ($referrals, $customer) {
            // 1. referred_customer_id を補完
            foreach ($referrals as $r) {
                if ($r->referred_customer_id === null) {
                    $r->referred_customer_id = $customer->id;
                    $r->save();
                }
            }

            // 2. 有効な紹介＝ linked/contracted/matured の最古1件（＝最初の紹介者）
            $active = $referrals
                ->whereIn('status', [
                    Referral::STATUS_LINKED,
                    Referral::STATUS_CONTRACTED,
                    Referral::STATUS_MATURED,
                ])
                ->sortBy('id')
                ->first();

            if (!$active) {
                return;
            }

            // 3. 最初の有効1件以外の linked は重複として却下
            foreach ($referrals as $r) {
                if ($r->id !== $active->id && $r->status === Referral::STATUS_LINKED) {
                    $r->update([
                        'status' => Referral::STATUS_REJECTED,
                        'reject_reason' => 'already_referred',
                    ]);
                }
            }

            // 4. 有効な紹介が linked で確定成約があれば contracted へ
            if ($active->status === Referral::STATUS_LINKED) {
                $contract = $customer->contracts()
                    ->where('status', '確定')
                    ->latest('contract_date')
                    ->first();
                if ($contract) {
                    $active->update([
                        'status' => Referral::STATUS_CONTRACTED,
                        'contract_id' => $contract->id,
                        'contracted_at' => now(),
                    ]);
                }
            }
        });
    }
}
