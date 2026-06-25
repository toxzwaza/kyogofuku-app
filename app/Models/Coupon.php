<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Coupon extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';
    public const STATUS_ARCHIVED = 'archived';

    public const DISCOUNT_FIXED = 'fixed';
    public const DISCOUNT_RATE = 'rate';

    protected $fillable = [
        'name',
        'description',
        'thumbnail_path',
        'thumbnail_disk',
        'terms_text',
        'discount_type',
        'discount_value',
        'valid_days',
        'valid_until_fixed',
        'combinable',
        'status',
    ];

    protected $casts = [
        'discount_value' => 'integer',
        'valid_days' => 'integer',
        'valid_until_fixed' => 'date',
        'combinable' => 'boolean',
    ];

    protected $appends = ['thumbnail_url'];

    public function customerCoupons()
    {
        return $this->hasMany(CustomerCoupon::class);
    }

    public function getThumbnailUrlAttribute(): ?string
    {
        if (!$this->thumbnail_path) {
            return null;
        }
        if (str_starts_with($this->thumbnail_path, 'http')) {
            return $this->thumbnail_path;
        }
        if (($this->thumbnail_disk ?? 's3') === 's3') {
            return Storage::disk('s3_public')->url(str_replace('\\', '/', $this->thumbnail_path));
        }

        return asset('storage/'.$this->thumbnail_path);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }
}
