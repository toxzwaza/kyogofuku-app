<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * GTMタグのマスタ（管理会社・GTM ID・種類・担当・用途・確度・メモ・紐付け店舗）
 */
class GtmTag extends Model
{
    /** 種類：イベントで選べるタグ / 社内共通（全LP） / HP用（参照のみ） */
    public const KINDS = [
        'event' => 'イベント',
        'company' => '社内共通',
        'site' => 'HP',
    ];

    /** 確度（担当者に確かめたものが「確認済」） */
    public const CONFIDENCES = ['確認済', '高', '中', '低'];

    public const CONFIRMED = '確認済';

    protected $fillable = [
        'company_name',
        'contact_person',
        'contact',
        'gtm_id',
        'kind',
        'purpose',
        'used_on',
        'confidence',
        'confirmed_at',
        'memo',
        'is_active',
    ];

    protected $attributes = [
        'kind' => 'event',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'confirmed_at' => 'date:Y-m-d',
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
     * 会社が空か、確度が確認済でない（＝まだ確かめていない）
     */
    public function isUnconfirmed(): bool
    {
        return blank($this->company_name) || $this->confidence !== self::CONFIRMED;
    }

    /**
     * 連絡先の一部を隠す（一覧表示用）。メールは @ の前を、電話などは末尾4文字以外を伏せる
     */
    public static function maskContact(?string $contact): ?string
    {
        if (blank($contact)) {
            return null;
        }
        $contact = trim($contact);
        if (str_contains($contact, '@')) {
            [$local, $domain] = explode('@', $contact, 2);

            return mb_substr($local, 0, 1).'***@'.$domain;
        }
        $len = mb_strlen($contact);
        if ($len <= 4) {
            return str_repeat('*', $len);
        }

        return str_repeat('*', $len - 4).mb_substr($contact, -4);
    }

    /**
     * イベント編集の選択肢（kind = event だけ。無効なタグも返す。選択中でなければ画面側で隠す。店舗の絞り込みも画面側で行う）
     *
     * @param  int|null  $includeId  選択中のタグ（event 以外になっていても保存済みの値が消えないよう含める）
     */
    public static function options(?int $includeId = null)
    {
        // 一般ユーザーでも他グループの紐付け店舗が消えないよう、店舗の可視範囲スコープは外す
        return static::with(['shops' => fn ($q) => $q->withoutGlobalScope('visibleGroup')->select('shops.id', 'shops.name')])
            ->where(fn ($q) => $q->where('kind', 'event')->when($includeId, fn ($q) => $q->orWhere('id', $includeId)))
            ->orderByRaw('company_name IS NULL')
            ->orderBy('company_name')
            ->orderBy('gtm_id')
            ->get()
            ->map(fn (GtmTag $t) => [
                'id' => $t->id,
                'company_name' => $t->company_name,
                'gtm_id' => $t->gtm_id,
                'kind' => $t->kind,
                'contact_person' => $t->contact_person,
                'purpose' => $t->purpose,
                'confidence' => $t->confidence,
                'memo' => $t->memo,
                'is_active' => (bool) $t->is_active,
                'shop_ids' => $t->shops->pluck('id')->values()->all(),
                'shop_names' => $t->shops->pluck('name')->values()->all(),
            ]);
    }
}
