<?php

namespace App\Services\Referral;

use App\Models\Customer;
use App\Models\PointLedger;
use App\Models\ReferralPoint;
use Illuminate\Support\Facades\DB;

/**
 * ポイント残高の増減を「残高更新＋台帳記録」を必ず同一トランザクションで行うための基盤サービス。
 * 残高（referral_points.balance）＝ point_ledger の合計、を常に保証する。
 */
class ReferralPointService
{
    /**
     * 残高にデルタを適用し、台帳に1行記録する。
     *
     * @param  int  $amount  +獲得 / -引換・返還
     * @param  string  $type  PointLedger::TYPE_*
     * @param  array{referral_id?:int|null, gift_card_id?:int|null, note?:string|null}  $meta
     */
    public function applyDelta(Customer $customer, int $amount, string $type, array $meta = []): PointLedger
    {
        return DB::transaction(function () use ($customer, $amount, $type, $meta) {
            // 残高行をロックして取得（なければ作成）
            $point = ReferralPoint::query()
                ->where('customer_id', $customer->id)
                ->lockForUpdate()
                ->first();

            if (!$point) {
                $point = ReferralPoint::create(['customer_id' => $customer->id, 'balance' => 0]);
                $point = ReferralPoint::query()->where('id', $point->id)->lockForUpdate()->first();
            }

            $newBalance = $point->balance + $amount;
            if ($newBalance < 0) {
                throw new \RuntimeException('ポイント残高が不足しています。');
            }

            $point->update(['balance' => $newBalance]);

            return PointLedger::create([
                'customer_id' => $customer->id,
                'amount' => $amount,
                'type' => $type,
                'referral_id' => $meta['referral_id'] ?? null,
                'gift_card_id' => $meta['gift_card_id'] ?? null,
                'note' => $meta['note'] ?? null,
            ]);
        });
    }

    public function balanceOf(Customer $customer): int
    {
        return (int) (ReferralPoint::query()->where('customer_id', $customer->id)->value('balance') ?? 0);
    }
}
