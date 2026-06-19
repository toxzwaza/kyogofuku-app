<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class DeviceRegistration extends Model
{
    protected $fillable = [
        'shop_id',
        'device_code',
        'token_hash',
        'label',
        'ip_address',
        'last_ip',
        'user_agent',
        'registered_by_user_id',
        'last_used_at',
        'revoked_at',
        'revoked_by_user_id',
    ];

    protected $casts = [
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $hidden = [
        'token_hash',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registered_by_user_id');
    }

    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by_user_id');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    /**
     * 生トークンを SHA-256 でハッシュ化（DB保存・照合用）
     */
    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * 有効（未revoke）な端末をトークンで検索
     */
    public static function findActiveByToken(?string $token): ?self
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        return static::query()
            ->whereNull('revoked_at')
            ->where('token_hash', static::hashToken($token))
            ->first();
    }

    /**
     * 一意な表示用端末コードを生成（例: AKB-7F3A21）
     */
    public static function generateUniqueDeviceCode(): string
    {
        do {
            $code = 'AKB-'.strtoupper(Str::random(6));
        } while (static::where('device_code', $code)->exists());

        return $code;
    }
}
