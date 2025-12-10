<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'start_at',
        'end_at',
        'all_day',
        'color',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'all_day' => 'boolean',
    ];

    /**
     * ユーザー（スタッフ）とのリレーション
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 参加者とのリレーション
     */
    public function participants()
    {
        return $this->hasMany(ScheduleParticipant::class, 'staff_schedule_id');
    }

    /**
     * 参加者（ユーザー）との多対多リレーション
     */
    public function participantUsers()
    {
        return $this->belongsToMany(User::class, 'schedule_participants', 'staff_schedule_id', 'user_id')
            ->withTimestamps();
    }
}
