<?php

namespace App\Services\Referral;

use App\Models\Customer;
use App\Models\PointLedger;
use App\Models\ReferralPoint;
use Illuminate\Support\Facades\DB;

/**
 * ポイント残高の増減を「残高更新＋台帳記録」を必ず同一トランザクションで行うための基盤サービス。
 * 残高（referral_points.balance / hirata_balance）＝ point_ledger の種別別合計、を常に保証する。
 *
 * ポイント種別：
 *  - referral（紹介ポイント）：ギフトカード交換・物品購入・譲渡すべて可
 *  - hirata（平田ポイント）：物品購入・譲渡のみ可（ギフトカード交換は不可）
 */
class ReferralPointService
{
    /**
     * 残高にデルタを適用し、台帳に1行記録する。
     *
     * @param  int  $amount  +獲得 / -引換・返還
     * @param  string  $type  PointLedger::TYPE_*
     * @param  array{referral_id?:int|null, gift_card_id?:int|null, note?:string|null}  $meta
     * @param  string  $pointType  PointLedger::POINT_TYPE_*（referral / hirata）
     */
    public function applyDelta(Customer $customer, int $amount, string $type, array $meta = [], string $pointType = PointLedger::POINT_TYPE_REFERRAL): PointLedger
    {
        return DB::transaction(function () use ($customer, $amount, $type, $meta, $pointType) {
            // 残高行をロックして取得（なければ作成）
            $point = ReferralPoint::query()
                ->where('customer_id', $customer->id)
                ->lockForUpdate()
                ->first();

            if (!$point) {
                $point = ReferralPoint::create(['customer_id' => $customer->id, 'balance' => 0, 'hirata_balance' => 0]);
                $point = ReferralPoint::query()->where('id', $point->id)->lockForUpdate()->first();
            }

            $column = $pointType === PointLedger::POINT_TYPE_HIRATA ? 'hirata_balance' : 'balance';
            $newBalance = (int) $point->{$column} + $amount;
            if ($newBalance < 0) {
                throw new \RuntimeException('ポイント残高が不足しています。');
            }

            $point->update([$column => $newBalance]);

            return PointLedger::create([
                'customer_id' => $customer->id,
                'amount' => $amount,
                'type' => $type,
                'point_type' => $pointType,
                'referral_id' => $meta['referral_id'] ?? null,
                'gift_card_id' => $meta['gift_card_id'] ?? null,
                'note' => $meta['note'] ?? null,
            ]);
        });
    }

    public function balanceOf(Customer $customer, string $pointType = PointLedger::POINT_TYPE_REFERRAL): int
    {
        $column = $pointType === PointLedger::POINT_TYPE_HIRATA ? 'hirata_balance' : 'balance';

        return (int) (ReferralPoint::query()->where('customer_id', $customer->id)->value($column) ?? 0);
    }

    /**
     * 紹介ポイント＋平田ポイントの合計残高。
     */
    public function totalBalanceOf(Customer $customer): int
    {
        $row = ReferralPoint::query()->where('customer_id', $customer->id)->first(['balance', 'hirata_balance']);

        return $row ? ((int) $row->balance + (int) $row->hirata_balance) : 0;
    }

    /**
     * 平田ポイントを優先して消費する（物品購入・譲渡の送出用）。
     * hirata から先に引き、足りなければ referral から引く。台帳は種別ごとに分けて記録する。
     *
     * @return array{hirata:int, referral:int} 実際に各種別から引いた額
     */
    public function spendPreferHirata(Customer $customer, int $amount, string $type, ?string $note = null): array
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('amount must be positive');
        }

        return DB::transaction(function () use ($customer, $amount, $type, $note) {
            $hirata = $this->balanceOf($customer, PointLedger::POINT_TYPE_HIRATA);
            $referral = $this->balanceOf($customer, PointLedger::POINT_TYPE_REFERRAL);
            if ($hirata + $referral < $amount) {
                throw new \RuntimeException('ポイント残高が不足しています。');
            }

            $fromHirata = min($hirata, $amount);
            $fromReferral = $amount - $fromHirata;

            if ($fromHirata > 0) {
                $this->applyDelta($customer, -$fromHirata, $type, ['note' => $note], PointLedger::POINT_TYPE_HIRATA);
            }
            if ($fromReferral > 0) {
                $this->applyDelta($customer, -$fromReferral, $type, ['note' => $note], PointLedger::POINT_TYPE_REFERRAL);
            }

            return ['hirata' => $fromHirata, 'referral' => $fromReferral];
        });
    }
}
