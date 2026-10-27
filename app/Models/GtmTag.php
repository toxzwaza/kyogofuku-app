<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * GTMタグのマスタ（管理会社・GTM ID・メモ・紐付け店舗）
 */
class GtmTag extends Model
{
    protected $fillable = [
        'company_name',
        'gtm_id',
        'memo',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function shops()
    {
        return $this->belongsToMany(Shop::class, 'gtm_tag_shop');
    }

    public function events()
    {
        return $this->hasMany(Event::class);
    }

    /**
     * イベント編集の選択肢（無効なタグも返す。選択中でなければ画面側で隠す。店舗の絞り込みも画面側で行う）
     */
    public static function options()
    {
        // 一般ユーザーでも他グループの紐付け店舗が消えないよう、店舗の可視範囲スコープは外す
        return static::with(['shops' => fn ($q) => $q->withoutGlobalScope('visibleGroup')->select('shops.id', 'shops.name')])
            ->orderBy('company_name')
            ->orderBy('gtm_id')
            ->get(['id', 'company_name', 'gtm_id', 'memo', 'is_active'])
            ->map(fn (GtmTag $t) => [
                'id' => $t->id,
                'company_name' => $t->company_name,
                'gtm_id' => $t->gtm_id,
                'memo' => $t->memo,
                'is_active' => (bool) $t->is_active,
                'shop_ids' => $t->shops->pluck('id')->values()->all(),
                'shop_names' => $t->shops->pluck('name')->values()->all(),
            ]);
    }
}
