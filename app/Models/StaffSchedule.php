<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StaffSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event_reservation_id',
        'photo_slot_id',
        'title',
        'description',
        'start_at',
        'end_at',
        'all_day',
        'expense_category',
        'is_public',
        'sync_to_google_calendar',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'end_at' => 'datetime',
        'all_day' => 'boolean',
        'is_public' => 'boolean',
        'sync_to_google_calendar' => 'boolean',
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

    /**
     * 予約とのリレーション
     */
    public function reservation()
    {
        return $this->belongsTo(EventReservation::class, 'event_reservation_id');
    }

    /**
     * 前撮り枠とのリレーション
     */
    public function photoSlot()
    {
        return $this->belongsTo(PhotoSlot::class);
    }

    /**
     * Google カレンダー同期レコードとのリレーション
     */
    public function googleCalendarEventSyncs()
    {
        return $this->hasMany(GoogleCalendarEventSync::class);
    }
}
