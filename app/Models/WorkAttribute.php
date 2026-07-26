<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkAttribute extends Model
{
    public const PATTERNS = ['A', 'B', 'C'];

    public const DAY_TYPE_WEEKDAY = 'weekday';

    public const DAY_TYPE_WEEKEND = 'weekend';

    /** 残業の決め方：ベース終業（baseEnd）超過。従来どおり（正社員） */
    public const OVERTIME_MODE_BASE_END = 'base_end';

    /** 残業の決め方：実働時間 − 残業閾値の超過（パート・時短） */
    public const OVERTIME_MODE_THRESHOLD = 'threshold';

    public const OVERTIME_MODES = [
        self::OVERTIME_MODE_BASE_END,
        self::OVERTIME_MODE_THRESHOLD,
    ];

    protected $fillable = [
        'name',
        'sort_order',
        'overtime_mode',
        'overtime_threshold_minutes',
    ];

    protected $casts = [
        'overtime_threshold_minutes' => 'integer',
    ];

    /** 残業が閾値方式か */
    public function usesThresholdOvertime(): bool
    {
        return $this->overtime_mode === self::OVERTIME_MODE_THRESHOLD;
    }

    public function patternTimes(): HasMany
    {
        return $this->hasMany(WorkAttributePatternTime::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
