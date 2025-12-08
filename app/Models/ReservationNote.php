<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReservationNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event_reservation_id',
        'content',
    ];

    /**
     * ユーザーとのリレーション
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 予約とのリレーション
     */
    public function reservation()
    {
        return $this->belongsTo(EventReservation::class, 'event_reservation_id');
    }
}

