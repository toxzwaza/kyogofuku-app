<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventUtmTracking extends Model
{
    use HasFactory;

    protected $fillable = [
        'session_id',
        'event_id',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_id',
        'event_reservation_id',
    ];

    /**
     * イベントとのリレーション
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * 予約とのリレーション
     */
    public function eventReservation()
    {
        return $this->belongsTo(EventReservation::class);
    }
}
