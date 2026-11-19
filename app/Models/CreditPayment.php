<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditPayment extends Model
{
    protected $fillable = [
        'credit_contract_id', 'due_month', 'amount', 'is_paid', 'paid_checked_at', 'paid_checked_by', 'note',
    ];

    protected $casts = [
        'due_month' => 'date:Y-m-d',
        'amount' => 'integer',
        'is_paid' => 'boolean',
        'paid_checked_at' => 'datetime',
    ];

    public function contract()
    {
        return $this->belongsTo(CreditContract::class, 'credit_contract_id');
    }

    public function checker()
    {
        return $this->belongsTo(User::class, 'paid_checked_by');
    }
}
