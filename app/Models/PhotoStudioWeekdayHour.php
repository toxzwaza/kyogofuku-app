<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhotoStudioWeekdayHour extends Model
{
    protected $fillable = ['photo_studio_id', 'weekday', 'start_time', 'end_time'];

    protected $casts = [
        'weekday' => 'integer',
    ];

    public function studio()
    {
        return $this->belongsTo(PhotoStudio::class, 'photo_studio_id');
    }
}
