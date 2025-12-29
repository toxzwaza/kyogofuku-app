<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventTimeslot extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'venue_id',
        'start_at',
        'capacity',
        'is_active',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'capacity' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * イベントとのリレーション
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * 会場とのリレーション
     */
    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * 残枠数を取得
     */
    public function getRemainingCapacityAttribute()
    {
        // 予約を取得（会場IDと時間が一致するもののみ）
        $reservationQuery = $this->event->reservations()
            ->where('reservation_datetime', $this->start_at->format('Y-m-d H:i:s'));
        
        // 予約枠に会場IDが設定されている場合、同じ会場の予約のみ取得
        if ($this->venue_id) {
            $reservationQuery->where('venue_id', $this->venue_id);
        } else {
            // 予約枠に会場IDが設定されていない場合、venue_idがnullの予約のみ取得
            $reservationQuery->whereNull('venue_id');
        }
        
        $reservationCount = $reservationQuery->count();
        
        return max(0, $this->capacity - $reservationCount);
    }
}

