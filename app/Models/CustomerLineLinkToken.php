<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CustomerLineLinkToken extends Model
{
    protected $fillable = [
        'token',
        'customer_id',
        'event_reservation_id',
        'shop_id',
        'suggested_label',
        'expires_at',
        'used_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public static function generateToken(): string
    {
        return (string) Str::uuid();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function eventReservation(): BelongsTo
    {
        return $this->belongsTo(EventReservation::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function isUsable(): bool
    {
        return $this->used_at === null && $this->expires_at->isFuture();
    }
}
