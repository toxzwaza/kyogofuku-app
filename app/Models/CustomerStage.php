<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerStage extends Model
{
    public const STAGE_BRONZE = 'bronze';
    public const STAGE_SILVER = 'silver';
    public const STAGE_GOLD = 'gold';
    public const STAGE_PLATINUM = 'platinum';

    public const STAGES = [
        self::STAGE_BRONZE,
        self::STAGE_SILVER,
        self::STAGE_GOLD,
        self::STAGE_PLATINUM,
    ];

    protected $fillable = [
        'customer_id',
        'stage',
        'matured_referrals_count',
        'last_evaluated_at',
    ];

    protected $casts = [
        'matured_referrals_count' => 'integer',
        'last_evaluated_at' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
