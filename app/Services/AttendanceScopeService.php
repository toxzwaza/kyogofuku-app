<?php

namespace App\Services;

use App\Models\AttendanceRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class AttendanceScopeService
{
    /**
     * 勤怠レコードのクエリに権限スコープを適用する。
     * 勤怠管理者: 全件
     * 管理者: 自身の所属店舗のレコードのみ
     * 一般: 空（使用しない想定）
     */
    public static function scopeForUser(Builder $query, User $user): Builder
    {
        if ($user->isAttendanceManager()) {
            return $query;
        }
        if ($user->isShopManager()) {
            $shopIds = $user->shops()->pluck('shops.id');
            return $query->whereIn('shop_id', $shopIds);
        }
        return $query->whereRaw('1 = 0'); // 一般ユーザーは対象レコードなし
    }

    /**
     * 指定レコードを指定ユーザーが承認可能か
     */
    public static function canApproveRecord(User $user, AttendanceRecord $record): bool
    {
        if ($user->isAttendanceManager()) {
            return true;
        }
        if ($user->isShopManager()) {
            return $user->shops()->where('shops.id', $record->shop_id)->exists();
        }
        return false;
    }

    /**
     * 勤怠管理画面へのアクセス権があるか
     */
    public static function canAccessManagement(User $user): bool
    {
        return $user->canManageAttendance();
    }

    /**
     * 承認依頼一覧へのアクセス権があるか
     */
    public static function canAccessApprovals(User $user): bool
    {
        return $user->canManageAttendance();
    }
}
