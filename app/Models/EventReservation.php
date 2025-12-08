<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventReservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'name',
        'email',
        'phone',
        'request_method',
        'postal_code',
        'venue_id',
        'has_visited_before',
        'address',
        'birth_date',
        'seijin_year',
        'referred_by_name',
        'reservation_datetime',
        'furigana',
        'school_name',
        'parking_usage',
        'parking_car_count',
        'considering_plans',
        'heard_from',
        'inquiry_message',
        'privacy_agreed',
    ];

    protected $casts = [
        'has_visited_before' => 'boolean',
        'birth_date' => 'date',
        'seijin_year' => 'integer',
        'parking_car_count' => 'integer',
        'considering_plans' => 'array',
        'privacy_agreed' => 'boolean',
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
     * メモとのリレーション
     */
    public function notes()
    {
        return $this->hasMany(ReservationNote::class, 'event_reservation_id');
    }
}

