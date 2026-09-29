<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Shop extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'group_key',
        'address',
        'phone',
        'image',
        'is_active',
        'line_group_id',
        'google_calendar_id',
        'device_password',
        'device_password_updated_at',
    ];

    /**
     * 店舗グループによる可視範囲の制限（グローバルスコープ）。
     *
     * ログイン中の一般ユーザー／店舗管理者には「自分の所属グループの店舗」だけを見せる。
     * これにより店舗選択ドロップダウンや店舗連動クエリが自動的にグループ内へ限定され、
     * 他グループ（例：福井から岡山）の顧客・イベント等がヒットしなくなる。
     *
     * 除外条件：
     *  - 未ログイン（公開ページ・ログイン画面）→ 制限しない
     *  - 勤怠管理者・システム管理者 → 全グループ横断で閲覧可（制限しない）
     */
    protected static function booted(): void
    {
        static::addGlobalScope('visibleGroup', function (Builder $builder) {
            $user = auth()->user();
            if (! $user || ! method_exists($user, 'visibleShopIds')) {
                return;
            }
            if (method_exists($user, 'isAttendanceManager') && $user->isAttendanceManager()) {
                return; // 管理者は全グループ横断
            }
            $builder->whereIn('shops.id', $user->visibleShopIds());
        });
    }

    protected $casts = [
        'is_active' => 'boolean',
        'device_password_updated_at' => 'datetime',
    ];

    protected $hidden = [
        'device_password',
    ];

    protected $appends = [
        'image_url',
    ];

    /**
     * 端末登録用パスワードとの多対多/従属
     */
    public function deviceRegistrations()
    {
        return $this->hasMany(DeviceRegistration::class);
    }

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
        if (! $this->image) {
            return null;
        }
        if (str_starts_with($this->image, 'http')) {
            return $this->image;
        }

        return asset('storage/'.$this->image);
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

    /**
     * 前撮り枠との多対多リレーション
     */
    public function photoSlots()
    {
        return $this->belongsToMany(PhotoSlot::class, 'photo_slot_shop');
    }

    /**
     * 制約テンプレートとの多対多リレーション
     */
    public function constraintTemplates()
    {
        return $this->belongsToMany(ConstraintTemplate::class, 'constraint_template_shop');
    }

    /**
     * 勤怠レコードとのリレーション
     */
    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }
}
