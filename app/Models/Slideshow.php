<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Slideshow extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
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
