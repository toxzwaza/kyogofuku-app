<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 成人式お支度の枠の席、またはお客様の登録（場所だけ／他店お支度／未定）。
 * 1行の意味はマイグレーション 2026_11_19_120000_create_seijin_prep_tables の注記を参照。
 */
class SeijinPrepSlot extends Model
{
    protected $fillable = [
        'seijin_prep_venue_id', 'prep_date', 'prep_time', 'seat_no', 'customer_id',
        'is_other_store', 'preferred_time', 'remarks', 'shop_id',
    ];

    protected $casts = [
        'seat_no' => 'integer',
        'is_other_store' => 'boolean',
    ];

    public function venue()
    {
        return $this->belongsTo(SeijinPrepVenue::class, 'seijin_prep_venue_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function shop()
    {
        return $this->belongsTo(Shop::class)->withoutGlobalScope('visibleGroup');
    }

    /** 時刻の枠の席か（場所だけ・他店・未定の登録ではない） */
    public function isSeat(): bool
    {
        return $this->prep_date !== null && $this->prep_time !== null;
    }

    public function scopeSeats(Builder $query): Builder
    {
        return $query->whereNotNull('seijin_prep_slots.prep_date')->whereNotNull('seijin_prep_slots.prep_time');
    }

    /** 時刻の枠に入っていない登録（場所だけ・他店・未定） */
    public function scopeUnscheduled(Builder $query): Builder
    {
        return $query->whereNull('seijin_prep_slots.prep_date')->whereNotNull('seijin_prep_slots.customer_id');
    }

    /**
     * ログインした人の店舗グループに絞る。
     * 場所のある行は場所の店舗グループで、場所のない行（他店・未定）は担当店舗のグループで絞る。担当店舗もない行は全員に見せる。
     */
    public function scopeVisibleToUser(Builder $query, ?User $user): Builder
    {
        $groups = PhotoStudio::visibleGroupsFor($user);
        if ($groups === null) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($groups) {
            $q->whereHas('venue', fn (Builder $v) => $v->where(function (Builder $w) use ($groups) {
                $w->whereNull('shop_group')->orWhereIn('shop_group', $groups ?: ['-']);
            }))->orWhere(function (Builder $q2) use ($groups) {
                $q2->whereNull('seijin_prep_slots.seijin_prep_venue_id')
                    ->where(function (Builder $q3) use ($groups) {
                        $q3->whereNull('seijin_prep_slots.shop_id')
                            ->orWhereIn('seijin_prep_slots.shop_id', Shop::withoutGlobalScope('visibleGroup')
                                ->whereIn('group_key', $groups ?: ['-'])->select('id'));
                    });
            });
        });
    }
}
