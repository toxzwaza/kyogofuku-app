<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralPoint extends Model
{
    protected $fillable = [
        'customer_id',
        'balance',
        'hirata_balance',
    ];

    protected $casts = [
        'balance' => 'integer',
        'hirata_balance' => 'integer',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
