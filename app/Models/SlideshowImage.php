<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SlideshowImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'slideshow_id',
        'path',
        'storage_disk',
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
     * Storage URLを取得（public=ローカル / s3=s3_public）
     */
    public function getUrlAttribute()
    {
        if (str_starts_with($this->path, 'http')) {
            return $this->path;
        }
        if (($this->storage_disk ?? 'public') === 's3') {
            $path = str_replace('\\', '/', $this->path);
            return Storage::disk('s3_public')->url($path);
        }
        return asset('storage/' . $this->path);
    }
}
