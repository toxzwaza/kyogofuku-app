<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PhotoSlot extends Model
{
    protected $fillable = [
        'photo_studio_id','shoot_date','shoot_time','seat_no','customer_id','details_undecided','remarks',
        'assignment_label','user_id','plan_id'
    ];

    protected $casts = [
        'details_undecided' => 'boolean',
        'seat_no' => 'integer',
    ];

    public function studio()
    {
        return $this->belongsTo(PhotoStudio::class,'photo_studio_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * 担当店舗との多対多リレーション
     */
    public function shops()
    {
        return $this->belongsToMany(Shop::class, 'photo_slot_shop');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * ログインした人の店舗グループの枠に絞る（PhotoStudio::scopeVisibleToUser と同じ考え方）。
     * スタジオが決まっている行はスタジオの店舗グループで、詳細未決定の行は担当店舗で絞る。
     */
    public function scopeVisibleToUser(Builder $query, ?User $user): Builder
    {
        $groups = PhotoStudio::visibleGroupsFor($user);
        if ($groups === null) {
            return $query;
        }
        $shopIds = $user->visibleShopIds();

        return $query->where(function (Builder $q) use ($groups, $shopIds) {
            $q->whereHas('studio', function (Builder $s) use ($groups) {
                $s->whereNull('shop_group');
                if (! empty($groups)) {
                    $s->orWhereIn('shop_group', $groups);
                }
            })->orWhere(function (Builder $u) use ($shopIds) {
                $u->whereNull('photo_slots.photo_studio_id')
                    ->where(function (Builder $w) use ($shopIds) {
                        // 担当店舗の有無は、ほかのグループの店舗も含めて調べる（絞り込んだままだと、他グループの店舗だけの枠が「担当なし」に見える）
                        $w->whereDoesntHave('shops', fn (Builder $sh) => $sh->withoutGlobalScope('visibleGroup'))
                            ->orWhereHas('shops', fn (Builder $sh) => $sh->withoutGlobalScope('visibleGroup')->whereIn('shops.id', $shopIds));
                    });
            });
        });
    }
}
