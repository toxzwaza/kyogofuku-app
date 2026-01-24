<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TimeslotTemplateSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'timeslot_template_id',
        'hour',
        'minute',
        'capacity',
    ];

    protected $casts = [
        'hour' => 'integer',
        'minute' => 'integer',
        'capacity' => 'integer',
    ];

    /**
     * テンプレートグループとのリレーション
     */
    public function template()
    {
        return $this->belongsTo(TimeslotTemplate::class, 'timeslot_template_id');
    }
}
