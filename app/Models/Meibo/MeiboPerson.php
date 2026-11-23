<?php

namespace App\Models\Meibo;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * 名簿の1人（成人式 DM の宛先。顧客になる前の方の個人情報）。
 * 統合ID（master_code）は印刷済みの QR に入っているので振り直さない。
 */
class MeiboPerson extends Model
{
    use HasFactory;

    public const STATUSES = ['送付可', '未達', '停止'];

    /** R11 成人名簿（2026-11-23 統合方針）の出所コード */
    public const R11_SOURCE = 'G_R11';

    protected $table = 'meibo_persons';

    protected $fillable = [
        'master_code', 'pref', 'seijin_year', 'birth_fy', 'name', 'kana', 'birth_date', 'gender',
        'zip', 'address', 'tel', 'mobile', 'guardian_name', 'guardian_kana',
        'source_versions', 'merge_count', 'dm_status', 'dm_note',
    ];

    protected $casts = [
        'seijin_year' => 'integer',
        'birth_fy' => 'integer',
        'merge_count' => 'integer',
        'birth_date' => 'date',
    ];

    public function dmLogs()
    {
        return $this->hasMany(MeiboDmLog::class, 'person_id');
    }

    /** 出所に R11 成人名簿を含む人（「/」区切りの文字列を前後の「/」つきで探す） */
    public function scopeFromR11(Builder $q): Builder
    {
        return $q->whereRaw("CONCAT('/', COALESCE(meibo_persons.source_versions, ''), '/') LIKE ?", ['%/' . self::R11_SOURCE . '/%']);
    }

    public function scopeNotFromR11(Builder $q): Builder
    {
        return $q->whereRaw("CONCAT('/', COALESCE(meibo_persons.source_versions, ''), '/') NOT LIKE ?", ['%/' . self::R11_SOURCE . '/%']);
    }

    public function hasR11(): bool
    {
        return in_array(self::R11_SOURCE, array_map('trim', explode('/', (string) $this->source_versions)), true);
    }
}
