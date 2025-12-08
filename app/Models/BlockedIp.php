<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlockedIp extends Model
{
    use HasFactory;

    protected $fillable = [
        'ip_address',
        'failure_count',
        'first_failed_at',
        'last_failed_at',
        'blocked_at',
        'unblocked_at',
        'unblocked_by_user_id',
        'unblock_reason',
        'is_active',
    ];

    protected $casts = [
        'first_failed_at' => 'datetime',
        'last_failed_at' => 'datetime',
        'blocked_at' => 'datetime',
        'unblocked_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    /**
     * ブロック解除したユーザー
     */
    public function unblockedBy()
    {
        return $this->belongsTo(User::class, 'unblocked_by_user_id');
    }
}
