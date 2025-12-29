<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SlideshowImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'slideshow_id',
        'path',
        'alt',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * スライドショーとのリレーション
     */
    public function slideshow()
    {
        return $this->belongsTo(Slideshow::class);
    }

    /**
     * Storage URLを取得
     */
    public function getUrlAttribute()
    {
        if (str_starts_with($this->path, 'http')) {
            return $this->path;
        }
        return asset('storage/' . $this->path);
    }
}
