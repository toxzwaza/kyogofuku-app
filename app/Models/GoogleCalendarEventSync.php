<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoogleCalendarEventSync extends Model
{
    use HasFactory;

    protected $fillable = [
        'staff_schedule_id',
        'shop_id',
        'google_event_id',
        'google_calendar_id',
    ];

    /**
     * スタッフスケジュールとのリレーション
     */
    public function staffSchedule()
    {
        return $this->belongsTo(StaffSchedule::class);
    }

    /**
     * 店舗とのリレーション
     */
    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }
}
