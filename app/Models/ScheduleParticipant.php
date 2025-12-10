<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScheduleParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_schedule_id',
        'user_id',
    ];

    /**
     * スケジュールとのリレーション
     */
    public function schedule()
    {
        return $this->belongsTo(StaffSchedule::class, 'staff_schedule_id');
    }

    /**
     * ユーザーとのリレーション
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
