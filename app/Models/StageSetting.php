<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StageSetting extends Model
{
    protected $fillable = [
        'stage',
        'min_referrals',
        'reward_rate_percent',
    ];

    protected $casts = [
        'min_referrals' => 'integer',
        'reward_rate_percent' => 'decimal:2',
    ];

    /**
     * 成立人数から該当ステージ設定（min_referrals を満たす最上位）を返す
     */
    public static function resolveForCount(int $maturedCount): ?self
    {
        return static::query()
            ->where('min_referrals', '<=', $maturedCount)
            ->orderByDesc('min_referrals')
            ->first();
    }
}
