<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class MediaFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'original_filename',
        'path',
        'storage_disk',
        'mime_type',
        'file_size',
        'width',
        'height',
        'alt',
        'tags',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'tags' => 'array',
    ];

    /**
     * このメディアを使用しているイベント画像
     */
    public function eventImages()
    {
        return $this->hasMany(EventImage::class);
    }

    /**
     * このメディアを使用しているスライドショー画像
     */
    public function slideshowImages()
    {
        return $this->hasMany(SlideshowImage::class);
    }

    /**
     * 参照カウント（イベント画像 + スライドショー画像）
     */
    public function getUsageCountAttribute(): int
    {
        return $this->eventImages()->count() + $this->slideshowImages()->count();
    }

    /**
     * Storage URLを取得
     */
    public function getUrlAttribute(): string
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

    /**
     * どこからも参照されていないか
     */
    public function isOrphaned(): bool
    {
        return $this->usage_count === 0;
    }
}
