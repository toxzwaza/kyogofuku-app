<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Slideshow extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'autoplay_interval',
        'autoplay_enabled',
        'fullscreen',
    ];

    protected $casts = [
        'autoplay_interval' => 'integer',
        'autoplay_enabled' => 'boolean',
        'fullscreen' => 'boolean',
    ];

    /**
     * スライドショー画像とのリレーション
     */
    public function images()
    {
        return $this->hasMany(SlideshowImage::class)->orderBy('sort_order');
    }

    /**
     * イベントとの多対多リレーション（中間テーブル: event_slideshow_positions）
     */
    public function events()
    {
        return $this->belongsToMany(Event::class, 'event_slideshow_positions')
            ->withPivot('position')
            ->withTimestamps();
    }
}
