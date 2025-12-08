<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shop_id',
        'action_type',
        'resource_type',
        'resource_id',
        'route_name',
        'url',
        'method',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    /**
     * ユーザーとのリレーション
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 店舗とのリレーション
     */
    public function shop()
    {
        return $this->belongsTo(Shop::class);
    }

    /**
     * アクションタイプの日本語名を取得
     */
    public function getActionTypeLabelAttribute()
    {
        return match($this->action_type) {
            'view' => '閲覧',
            'create' => '作成',
            'update' => '更新',
            'delete' => '削除',
            'login' => 'ログイン',
            'logout' => 'ログアウト',
            'export' => 'エクスポート',
            'import' => 'インポート',
            default => $this->action_type,
        };
    }

    /**
     * リソースタイプの日本語名を取得
     */
    public function getResourceTypeLabelAttribute()
    {
        return match($this->resource_type) {
            'Event' => 'イベント',
            'EventReservation' => '予約',
            'EventImage' => 'イベント画像',
            'EventTimeslot' => '予約枠',
            'Shop' => '店舗',
            'User' => 'スタッフ',
            'Venue' => '会場',
            'ReservationNote' => '予約メモ',
            default => $this->resource_type ?? '-',
        };
    }
}

