<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class EventImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'path',
        'storage_disk',
        'webp_path',
        'alt',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * イベントとのリレーション
     */
    public function event()
    {
        return $this->belongsTo(Event::class);
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

    /**
     * WebP画像のURLを取得
     */
    public function getWebpUrlAttribute()
    {
        if (!$this->webp_path) {
            return null;
        }
        if (str_starts_with($this->webp_path, 'http')) {
            return $this->webp_path;
        }
        if (($this->storage_disk ?? 'public') === 's3') {
            $path = str_replace('\\', '/', $this->webp_path);
            return Storage::disk('s3_public')->url($path);
        }
        return asset('storage/' . $this->webp_path);
    }
}

