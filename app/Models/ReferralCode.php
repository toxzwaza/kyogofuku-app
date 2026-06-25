<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReferralCode extends Model
{
    protected $fillable = [
        'customer_id',
        'code',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * 衝突しない紹介コードを生成（大文字英数字・既定8桁）
     */
    public static function generateUniqueCode(int $length = 8): string
    {
        do {
            $code = strtoupper(Str::random($length));
        } while (static::query()->where('code', $code)->exists());

        return $code;
    }
}
