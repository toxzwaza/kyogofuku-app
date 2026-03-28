<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkAttributePatternTime extends Model
{
    protected $fillable = [
        'work_attribute_id',
        'pattern',
        'day_type',
        'work_start_time',
        'work_end_time',
    ];

    public function workAttribute(): BelongsTo
    {
        return $this->belongsTo(WorkAttribute::class);
    }
}
