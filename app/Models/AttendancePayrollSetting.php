<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AttendancePayrollSetting extends Model
{
    protected $fillable = [
        'start_early_threshold_minutes',
        'start_rounding_unit_minutes',
        'overtime_rounding_unit_minutes',
        'overtime_discard_remainder_upto_minutes',
    ];

    public static function current(): self
    {
        return static::query()->orderBy('id')->firstOrFail();
    }
}
