<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PointLedger extends Model
{
    public const TYPE_REFERRER_REWARD = 'referrer_reward';             // 紹介者へ 成約金額×% 付与
    public const TYPE_REFERRED_BONUS = 'referred_bonus';              // 被紹介者へ 固定pt 付与
    public const TYPE_GIFT_CARD_REDEEM = 'gift_card_redeem';          // ギフトカード引換でマイナス
    public const TYPE_GIFT_CARD_CANCEL_REFUND = 'gift_card_cancel_refund'; // 取消で返還（プラス）
    public const TYPE_ADJUST = 'adjust';                              // 手動調整

    protected $table = 'point_ledger';

    protected $fillable = [
        'customer_id',
        'amount',
        'type',
        'referral_id',
        'gift_card_id',
        'note',
    ];

    protected $casts = [
        'amount' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function referral()
    {
        return $this->belongsTo(Referral::class);
    }

    public function giftCard()
    {
        return $this->belongsTo(GiftCard::class);
    }
}
