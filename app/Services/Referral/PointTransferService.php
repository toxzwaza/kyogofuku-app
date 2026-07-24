<?php

namespace App\Services\Referral;

use App\Models\Customer;
use App\Models\PointLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * 顧客間のポイント譲渡（管理画面でスタッフが代行）。
 * 等価（送り手 -pt / 受け手 +pt）で、両者を同一トランザクションで処理する。
 */
class PointTransferService
{
    public function __construct(private ReferralPointService $points)
    {
    }

    public function transfer(Customer $from, Customer $to, int $points, ?string $note = null): void
    {
        if ($points <= 0) {
            throw ValidationException::withMessages(['points' => '譲渡ポイントは1以上で指定してください。']);
        }
        if ($from->id === $to->id) {
            throw ValidationException::withMessages(['to_customer_id' => '同じ顧客には譲渡できません。']);
        }
        if ($this->points->totalBalanceOf($from) < $points) {
            throw ValidationException::withMessages(['points' => 'ポイント残高が不足しています。']);
        }

        $suffix = $note ? "（{$note}）" : '';
        $outNote = "「{$to->name}」様へ譲渡{$suffix}";
        $inNote = "「{$from->name}」様から譲受{$suffix}";

        // 平田ポイントを優先して送出し、受け手にも同じ種別で渡す（用途制限を維持）
        $fromHirata = min($this->points->balanceOf($from, PointLedger::POINT_TYPE_HIRATA), $points);
        $fromReferral = $points - $fromHirata;

        DB::transaction(function () use ($from, $to, $fromHirata, $fromReferral, $outNote, $inNote) {
            if ($fromHirata > 0) {
                $this->points->applyDelta($from, -$fromHirata, PointLedger::TYPE_TRANSFER_OUT, ['note' => $outNote], PointLedger::POINT_TYPE_HIRATA);
                $this->points->applyDelta($to, $fromHirata, PointLedger::TYPE_TRANSFER_IN, ['note' => $inNote], PointLedger::POINT_TYPE_HIRATA);
            }
            if ($fromReferral > 0) {
                $this->points->applyDelta($from, -$fromReferral, PointLedger::TYPE_TRANSFER_OUT, ['note' => $outNote], PointLedger::POINT_TYPE_REFERRAL);
                $this->points->applyDelta($to, $fromReferral, PointLedger::TYPE_TRANSFER_IN, ['note' => $inNote], PointLedger::POINT_TYPE_REFERRAL);
            }
        });
    }
}
