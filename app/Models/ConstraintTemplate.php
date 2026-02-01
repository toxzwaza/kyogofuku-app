<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConstraintTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'body',
        'is_active',
        'display_settings',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'display_settings' => 'array',
    ];

    /** 表示設定のデフォルト値 */
    public static function defaultDisplaySettings(): array
    {
        return [
            'padding_mm' => 6,
            'line_height' => 1.25,
            'font_size_px' => 8,
            'signature_height_px' => 80,
            'checkbox_size_px' => 10,
        ];
    }

    public function getDisplaySettings(): array
    {
        $saved = $this->display_settings ?? [];
        return array_merge(self::defaultDisplaySettings(), $saved);
    }

    public function shops()
    {
        return $this->belongsToMany(Shop::class, 'constraint_template_shop');
    }

    public function customerConstraints()
    {
        return $this->hasMany(CustomerConstraint::class);
    }
}
