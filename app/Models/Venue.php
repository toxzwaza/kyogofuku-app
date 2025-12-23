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
        'image',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'image_url',
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

    /**
     * Storage URLを取得
     */
    public function getImageUrlAttribute()
    {
        if (!$this->image) {
            return null;
        }
        if (str_starts_with($this->image, 'http')) {
            return $this->image;
        }
        return asset('storage/' . $this->image);
    }
}

