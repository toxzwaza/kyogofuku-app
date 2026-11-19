<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 成人式のお支度場所（自社会場／提携美容室）
 */
class SeijinPrepVenue extends Model
{
    /** 定員（レーン数）の上限（1枠あたり） */
    public const MAX_CAPACITY = 20;

    public const KIND_LABELS = ['own' => '自社会場', 'salon' => '提携美容室'];

    /** 枠の刻み（分）の選択肢 */
    public const INTERVALS = [10, 15, 20, 30, 40, 60];

    protected $fillable = [
        'name', 'kind', 'shop_group', 'is_active', 'sort_order', 'address', 'phone', 'remarks',
        'default_start_time', 'default_end_time', 'default_capacity', 'slot_interval_minutes',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'default_capacity' => 'integer',
        'slot_interval_minutes' => 'integer',
    ];

    public function slots()
    {
        return $this->hasMany(SeijinPrepSlot::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('seijin_prep_venues.is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** ログインした人の店舗グループの場所に絞る（考え方は前撮りのスタジオと同じ） */
    public function scopeVisibleToUser(Builder $query, ?User $user): Builder
    {
        $groups = PhotoStudio::visibleGroupsFor($user);
        if ($groups === null) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($groups) {
            $q->whereNull('seijin_prep_venues.shop_group');
            if (! empty($groups)) {
                $q->orWhereIn('seijin_prep_venues.shop_group', $groups);
            }
        });
    }

    public function isVisibleTo(?User $user): bool
    {
        $groups = PhotoStudio::visibleGroupsFor($user);

        return $groups === null || $this->shop_group === null || in_array($this->shop_group, $groups, true);
    }

    public function interval(): int
    {
        return in_array((int) $this->slot_interval_minutes, self::INTERVALS, true) ? (int) $this->slot_interval_minutes : 30;
    }

    /** その時刻が、この場所の刻みに合っているか（0:00 から数えて） */
    public function isOnStep(string $hm): bool
    {
        [$h, $m] = array_map('intval', explode(':', $hm));

        return (($h * 60 + $m) % $this->interval()) === 0;
    }
}
