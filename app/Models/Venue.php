<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Venue extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'address',
        'phone',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * イベントとの多対多リレーション
     */
    public function events()
    {
        return $this->belongsToMany(Event::class, 'event_venue');
    }

    /**
     * 予約とのリレーション
     */
    public function reservations()
    {
        return $this->hasMany(EventReservation::class);
    }
}

