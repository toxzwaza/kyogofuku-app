<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceRecord extends Model
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_APPLIED = 'applied';
    public const STATUS_APPROVED = 'approved';

    protected $fillable = [
        'user_id',
        'shop_id',
        'date',
        'clock_in_at',
        'clock_out_at',
        'status',
        'application_reason',
        'is_manual',
        'approved_at',
        'approved_by',
        'version',
    ];

    protected $casts = [
        'date' => 'date',
        'clock_in_at' => 'datetime',
        'clock_out_at' => 'datetime',
        'approved_at' => 'datetime',
        'is_manual' => 'boolean',
    ];

    /**
     * ユーザーとのリレーション
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 店舗とのリレーション
     */
    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * 休憩とのリレーション
     */
    public function breaks(): HasMany
    {
        return $this->hasMany(AttendanceBreak::class, 'attendance_record_id')->orderBy('start_at');
    }

    /**
     * 承認者とのリレーション
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * 未申請（draft）かどうか
     */
    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    /**
     * 申請済（applied）かどうか
     */
    public function isApplied(): bool
    {
        return $this->status === self::STATUS_APPLIED;
    }

    /**
     * 承認済（approved）かどうか
     */
    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    /**
     * 休憩中かどうか（休憩開始ありかつ終了なし）
     */
    public function isOnBreak(): bool
    {
        return $this->breaks()->whereNull('end_at')->exists();
    }

    /**
     * 勤務中かどうか（出勤あり、退勤なし、休憩中でない）
     */
    public function isWorking(): bool
    {
        return $this->clock_in_at !== null
            && $this->clock_out_at === null
            && !$this->isOnBreak();
    }

    /**
     * 実勤務時間を分で返す（休憩除く）
     */
    public function getWorkMinutesAttribute(): int
    {
        if ($this->clock_in_at === null || $this->clock_out_at === null) {
            return 0;
        }
        $totalMinutes = (int) ($this->clock_out_at->diffInSeconds($this->clock_in_at) / 60);
        $breakMinutes = $this->breaks
            ->filter(fn ($b) => $b->end_at !== null)
            ->sum(fn ($b) => (int) ($b->end_at->diffInSeconds($b->start_at) / 60));
        return max(0, $totalMinutes - $breakMinutes);
    }
}
