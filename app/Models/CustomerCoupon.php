<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerCoupon extends Model
{
    public const STATUS_HELD = 'held';
    public const STATUS_USED = 'used';
    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'customer_id',
        'coupon_id',
        'status',
        'valid_until',
        'used_at',
        'used_by_user_id',
        'used_shop_id',
    ];

    protected $casts = [
        'valid_until' => 'date',
        'used_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function coupon()
    {
        return $this->belongsTo(Coupon::class);
    }

    public function usedBy()
    {
        return $this->belongsTo(User::class, 'used_by_user_id');
    }

    public function isUsable(): bool
    {
        return $this->status === self::STATUS_HELD;
    }
}
