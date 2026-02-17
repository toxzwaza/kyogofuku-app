<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'slug_aliases',
        'title',
        'description',
        'form_type',
        'start_at',
        'end_at',
        'is_public',
        'gtm_id',
        'success_text',
        'cta_background_path',
        'cta_web_button_path',
        'cta_phone_button_path',
        'cta_storage_disk',
    ];

    protected $casts = [
        'start_at' => 'date',
        'end_at' => 'date',
        'is_public' => 'boolean',
        'slug_aliases' => 'array',
    ];

    protected $appends = [
        'cta_background_url',
        'cta_web_button_url',
        'cta_phone_button_url',
    ];

    /**
     * CTA画像のURLを取得（path が null のときは null）
     */
    private function getCtaUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }
        if (str_starts_with($path, 'http')) {
            return $path;
        }
        if (($this->cta_storage_disk ?? 'public') === 's3') {
            $path = str_replace('\\', '/', $path);
            return Storage::disk('s3_public')->url($path);
        }
        return asset('storage/' . $path);
    }

    public function getCtaBackgroundUrlAttribute(): ?string
    {
        return $this->getCtaUrl($this->cta_background_path);
    }

    public function getCtaWebButtonUrlAttribute(): ?string
    {
        return $this->getCtaUrl($this->cta_web_button_path);
    }

    public function getCtaPhoneButtonUrlAttribute(): ?string
    {
        return $this->getCtaUrl($this->cta_phone_button_path);
    }

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

    /**
     * UTMトラッキングとのリレーション
     */
    public function utmTrackings()
    {
        return $this->hasMany(EventUtmTracking::class);
    }
}

