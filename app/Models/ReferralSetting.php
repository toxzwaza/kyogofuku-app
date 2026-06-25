<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReferralSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /**
     * 既定値（seeder未投入でも安全に動くようコード側でも保持）
     */
    public const DEFAULTS = [
        'referred_bonus_points' => '10000',     // 被紹介者特典の固定ポイント
        'gift_card_unit' => '500',              // ギフトカード引換単位（円）
        'referral_expire_months' => '6',        // 紹介の有効期限（月）
        'maturation_months' => '1',             // 成約→確定までの月数（クーリングオフ）
        'contract_amount_tax_rate' => '10',     // 成約金額の税率%（税込→税抜換算用）
        'point_base_tax_excluded' => '1',       // ポイント基準額を税抜にするか（1=税抜）
    ];

    public static function get(string $key): ?string
    {
        $row = static::query()->where('key', $key)->first();
        if ($row) {
            return $row->value;
        }

        return self::DEFAULTS[$key] ?? null;
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $v = static::get($key);

        return $v === null ? $default : (int) $v;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $v = static::get($key);

        return $v === null ? $default : in_array($v, ['1', 'true', 'yes'], true);
    }

    public static function set(string $key, string $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
