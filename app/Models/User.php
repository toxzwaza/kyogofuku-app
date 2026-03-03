<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'furigana',
        'email',
        'login_id',
        'password',
        'theme_color',
        'attendance_role',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    /**
     * 店舗との多対多リレーション
     */
    public function shops()
    {
        return $this->belongsToMany(Shop::class, 'shop_user')->withPivot('main');
    }

    /**
     * 予約メモとのリレーション
     */
    public function reservationNotes()
    {
        return $this->hasMany(ReservationNote::class);
    }

    /**
     * 顧客メモとのリレーション
     */
    public function customerNotes()
    {
        return $this->hasMany(CustomerNote::class);
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
     * 勤怠レコードとのリレーション
     */
    public function attendanceRecords()
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * 管理者（所属店舗の勤怠確認・承認）かどうか
     */
    public function isShopManager(): bool
    {
        return $this->attendance_role === 'shop_manager';
    }

    /**
     * 勤怠管理者（全店舗の勤怠確認・承認）かどうか
     */
    public function isAttendanceManager(): bool
    {
        return $this->attendance_role === 'attendance_manager';
    }

    /**
     * 勤怠の閲覧・承認権限があるか
     */
    public function canManageAttendance(): bool
    {
        return $this->isShopManager() || $this->isAttendanceManager();
    }
}
