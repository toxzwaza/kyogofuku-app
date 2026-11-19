<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PhotoStudio extends Model
{
    /** 定員の上限（1枠あたり） */
    public const MAX_CAPACITY = 10;

    protected $fillable = [
        'name', 'address', 'remarks',
        'shop_group', 'is_active', 'default_start_time', 'default_end_time', 'default_capacity',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'default_capacity' => 'integer',
    ];

    public function photoSlots()
    {
        return $this->hasMany(PhotoSlot::class);
    }

    /** 曜日ごとの営業時間（既定と違う曜日だけ） */
    public function weekdayHours()
    {
        return $this->hasMany(PhotoStudioWeekdayHour::class)->orderBy('weekday');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('photo_studios.is_active', true);
    }

    /**
     * ログインした人の店舗グループのスタジオに絞る。
     * システム管理者は全部。店舗グループが未設定のスタジオは誰にでも見せる（本番でグループを付けるまでの間のため）。
     * 夜間のコマンドや LINE マイページで知らないうちに絞られないよう、グローバルスコープにはしない。
     */
    public function scopeVisibleToUser(Builder $query, ?User $user): Builder
    {
        $groups = static::visibleGroupsFor($user);
        if ($groups === null) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($groups) {
            $q->whereNull('photo_studios.shop_group');
            if (! empty($groups)) {
                $q->orWhereIn('photo_studios.shop_group', $groups);
            }
        });
    }

    /**
     * 見てよい店舗グループの一覧。null は「全部見てよい」（システム管理者・未ログインのコマンド等）。
     */
    public static function visibleGroupsFor(?User $user): ?array
    {
        if (! $user) {
            return null;
        }
        if ($user->isAttendanceManager()) {
            return null;
        }

        return Shop::withoutGlobalScope('visibleGroup')
            ->whereIn('id', $user->visibleShopIds())
            ->whereNotNull('group_key')
            ->distinct()
            ->pluck('group_key')
            ->values()
            ->all();
    }

    public function isVisibleTo(?User $user): bool
    {
        $groups = static::visibleGroupsFor($user);

        return $groups === null || $this->shop_group === null || in_array($this->shop_group, $groups, true);
    }

    /** 営業時間（既定）が登録されているか */
    public function hasHours(): bool
    {
        return $this->default_start_time !== null && $this->default_end_time !== null;
    }
}
