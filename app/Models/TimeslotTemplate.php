<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeslotTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    /**
     * テンプレートスロットとのリレーション
     */
    public function slots()
    {
        return $this->hasMany(TimeslotTemplateSlot::class);
    }
}
