<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CreditCompany extends Model
{
    protected $fillable = ['name', 'aliases', 'needs_check', 'sort_order', 'is_active'];

    protected $casts = [
        'aliases' => 'array',
        'needs_check' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function contracts()
    {
        return $this->hasMany(CreditContract::class);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }

    /** 表記（正式名・別名）から会社を探す。見つからなければ null */
    public static function findByLabel(?string $label): ?self
    {
        $label = trim(mb_convert_kana((string) $label, 'asKV'));
        if ($label === '') {
            return null;
        }
        foreach (static::query()->get() as $company) {
            $names = array_merge([$company->name], $company->aliases ?? []);
            foreach ($names as $n) {
                if (mb_strtolower(trim(mb_convert_kana($n, 'asKV'))) === mb_strtolower($label)) {
                    return $company;
                }
            }
        }
        return null;
    }
}
