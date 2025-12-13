<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhotoStudio extends Model
{
    protected $fillable = ['name','address','remarks'];

    public function photoSlots()
    {
        return $this->hasMany(PhotoSlot::class);
    }
}
