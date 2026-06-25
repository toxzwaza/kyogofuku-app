<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Referral extends Model
{
    public const STATUS_LINKED = 'linked';         // LINE紐付け完了・紹介成立待ち
    public const STATUS_CONTRACTED = 'contracted'; // 被紹介者が成約（仮確定）
    public const STATUS_MATURED = 'matured';       // 成約から確定期間経過・特典付与済
    public const STATUS_EXPIRED = 'expired';       // 期限切れ（未成約）
    public const STATUS_REJECTED = 'rejected';     // 自己紹介・既存顧客・重複等で無効

    protected $fillable = [
        'referrer_customer_id',
        'referred_line_user_id',
        'referred_customer_id',
        'status',
        'contract_id',
        'contracted_at',
        'matured_at',
        'expires_at',
        'reject_reason',
    ];

    protected $casts = [
        'contracted_at' => 'datetime',
        'matured_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function referrer()
    {
        return $this->belongsTo(Customer::class, 'referrer_customer_id');
    }

    public function referredCustomer()
    {
        return $this->belongsTo(Customer::class, 'referred_customer_id');
    }

    public function contract()
    {
        return $this->belongsTo(Contract::class);
    }
}
