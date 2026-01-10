<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'description',
        'form_type',
        'start_at',
        'end_at',
        'is_public',
        'gtm_id',
    ];

    protected $casts = [
        'start_at' => 'date',
        'end_at' => 'date',
        'is_public' => 'boolean',
    ];

    /**
     * イベント画像とのリレーション
     */
    public function images()
    {
        return $this->hasMany(EventImage::class)->orderBy('sort_order');
    }

    /**
     * 予約データとのリレーション
     */
    public function reservations()
    {
        return $this->hasMany(EventReservation::class);
    }

    /**
     * 予約枠とのリレーション
     */
    public function timeslots()
    {
        return $this->hasMany(EventTimeslot::class);
    }

    /**
     * 店舗との多対多リレーション
     */
    public function shops()
    {
        return $this->belongsToMany(Shop::class, 'event_shop');
    }

    /**
     * 会場との多対多リレーション
     */
    public function venues()
    {
        return $this->belongsToMany(Venue::class, 'event_venue');
    }

    /**
     * 資料との多対多リレーション
     */
    public function documents()
    {
        return $this->belongsToMany(Document::class, 'event_document');
    }

    /**
     * スライドショーとの多対多リレーション（中間テーブル: event_slideshow_positions）
     */
    public function slideshows()
    {
        return $this->belongsToMany(Slideshow::class, 'event_slideshow_positions')
            ->withPivot('position')
            ->withTimestamps();
    }
}

