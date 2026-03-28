<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyCalendarDay extends Model
{
    protected $fillable = [
        'calendar_date',
        'pattern',
    ];

    protected $casts = [
        'calendar_date' => 'date',
    ];
}
