<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'address',
        'phone',
        'image',
        'is_active',
        'line_group_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'image_url',
    ];

    /**
     * イベントとの多対多リレーション
     */
    public function events()
    {
        return $this->belongsToMany(Event::class, 'event_shop');
    }

    /**
     * ユーザー（スタッフ）との多対多リレーション
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'shop_user');
    }

    /**
     * Storage URLを取得
     */
    public function getImageUrlAttribute()
    {
        if (!$this->image) {
            return null;
        }
        if (str_starts_with($this->image, 'http')) {
            return $this->image;
        }
        return asset('storage/' . $this->image);
    }

    /**
     * アクティビティログとのリレーション
     */
    public function activityLogs()
    {
        return $this->hasMany(ActivityLog::class);
    }

    /**
     * スケジュールとのリレーション
     */
    public function schedules()
    {
        return $this->hasMany(StaffSchedule::class);
    }
}

