<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'pdf_path',
        'thumbnail_path',
    ];

    /**
     * イベントとの多対多リレーション
     */
    public function events()
    {
        return $this->belongsToMany(Event::class, 'event_document');
    }

    /**
     * 予約とのリレーション
     */
    public function reservations()
    {
        return $this->hasMany(EventReservation::class);
    }

    /**
     * 画像とのリレーション
     */
    public function images()
    {
        return $this->hasMany(DocumentImage::class)->orderBy('sort_order');
    }

    /**
     * PDFのURLを取得
     */
    public function getPdfUrlAttribute()
    {
        if (!$this->pdf_path) {
            return null;
        }
        if (str_starts_with($this->pdf_path, 'http')) {
            return $this->pdf_path;
        }
        return asset('storage/' . $this->pdf_path);
    }

    /**
     * サムネイル画像のURLを取得
     */
    public function getThumbnailUrlAttribute()
    {
        // まずthumbnail_pathを確認
        if ($this->thumbnail_path) {
            if (str_starts_with($this->thumbnail_path, 'http')) {
                return $this->thumbnail_path;
            }
            return asset('storage/' . $this->thumbnail_path);
        }
        
        // thumbnail_pathがない場合は、最初の画像を使用（後方互換性のため）
        $firstImage = $this->images()->orderBy('sort_order')->first();
        if ($firstImage) {
            return $firstImage->image_url;
        }
        
        return null;
    }
}

