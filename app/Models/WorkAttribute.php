<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkAttribute extends Model
{
    public const PATTERNS = ['A', 'B', 'C'];

    public const DAY_TYPE_WEEKDAY = 'weekday';

    public const DAY_TYPE_WEEKEND = 'weekend';

    protected $fillable = [
        'name',
        'sort_order',
    ];

    public function patternTimes(): HasMany
    {
        return $this->hasMany(WorkAttributePatternTime::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
