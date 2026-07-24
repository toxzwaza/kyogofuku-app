<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GiftCard extends Model
{
    public const STATUS_ISSUED = 'issued';     // 店舗で発行済（ポイント減算済）
    public const STATUS_CANCELED = 'canceled'; // 取消（ポイント返還済）

    protected $fillable = [
        'customer_id',
        'amount',
        'points_spent',
        'status',
        'issued_by_user_id',
        'issued_shop_id',
        'issued_at',
        'canceled_at',
    ];

    protected $casts = [
        'amount' => 'integer',
        'points_spent' => 'integer',
        'issued_at' => 'datetime',
        'canceled_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function issuedBy()
    {
        return $this->belongsTo(User::class, 'issued_by_user_id');
    }

    public function issuedShop()
    {
        return $this->belongsTo(Shop::class, 'issued_shop_id');
    }

    public function isCancelable(): bool
    {
        return $this->status === self::STATUS_ISSUED;
    }
}
