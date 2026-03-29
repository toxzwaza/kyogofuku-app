<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerLineContact extends Model
{
    protected $fillable = [
        'customer_id',
        'event_reservation_id',
        'shop_id',
        'line_user_id',
        'label',
    ];

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

    public function messages(): HasMany
    {
        return $this->hasMany(CustomerLineMessage::class);
    }
}
