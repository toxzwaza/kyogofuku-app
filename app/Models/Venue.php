<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Venue extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'address',
        'phone',
        'image',
        'storage_disk',
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
     * Storage URLを取得（public=ローカル / s3=s3_public）
     */
    public function getImageUrlAttribute()
    {
        if (! $this->image) {
            return null;
        }
        if (str_starts_with($this->image, 'http')) {
            return $this->image;
        }
        if (($this->storage_disk ?? 'public') === 's3') {
            $path = str_replace('\\', '/', $this->image);
            return Storage::disk('s3_public')->url($path);
        }
        return asset('storage/' . $this->image);
    }
}

