<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLeave extends Model
{
    public const TYPE_PAID = 'paid';        // 有給
    public const TYPE_SPECIAL = 'special';  // 特別休暇
    public const TYPE_ABSENCE = 'absence';  // 欠勤

    public const TYPES = [
        self::TYPE_PAID,
        self::TYPE_SPECIAL,
        self::TYPE_ABSENCE,
    ];

    protected $fillable = [
        'user_id',
        'date',
        'leave_type',
        'note',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
