<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * 共有ノート（画面での呼び名。内部名は incidents）。
 */
class Incident extends Model
{
    /** 区分の候補（これ以外は「その他」の自由入力） */
    public const CATEGORIES = ['振袖', '袴', '一般呉服', '前撮り', 'その他'];

    /** 種類の候補（これ以外は「その他」の自由入力） */
    public const KINDS = ['クレーム', '問い合わせ', '連絡', '社内のミス・トラブル', 'ヒヤリ・ハット', '気づき・提案', 'その他'];

    /** 一覧のタブ（種類のまとまり） */
    public const TABS = [
        'all'  => null,
        'cust' => ['クレーム', '問い合わせ', '連絡'],
        'miss' => ['社内のミス・トラブル', 'ヒヤリ・ハット'],
        'idea' => ['気づき・提案'],
    ];

    /** 「その場でした対応」が任意の種類 */
    public const ACTION_OPTIONAL_KINDS = ['ヒヤリ・ハット'];

    /** 「その場でした対応」を聞かない種類 */
    public const ACTION_SKIPPED_KINDS = ['気づき・提案'];

    public const GROUPS = ['okayama' => '岡山グループ', 'fukui' => '福井グループ'];

    protected $fillable = [
        'group_key', 'occurred_on', 'shop_name', 'category', 'kind',
        'title', 'what', 'action', 'consult', 'created_by',
    ];

    protected $casts = [
        'occurred_on' => 'date:Y-m-d',
    ];

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function comments()
    {
        return $this->hasMany(IncidentComment::class)->orderBy('created_at')->orderBy('id');
    }

    public function scopeInTab(Builder $q, ?string $tab): Builder
    {
        $kinds = self::TABS[$tab] ?? null;

        return $kinds ? $q->whereIn('kind', $kinds) : $q;
    }

    /** 種類に対して「その場でした対応」が必須か */
    public static function actionRequired(string $kind): bool
    {
        return ! in_array($kind, self::ACTION_OPTIONAL_KINDS, true)
            && ! in_array($kind, self::ACTION_SKIPPED_KINDS, true);
    }

    public static function actionSkipped(string $kind): bool
    {
        return in_array($kind, self::ACTION_SKIPPED_KINDS, true);
    }
}
