<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhotoSlot extends Model
{
    protected $fillable = [
        'photo_studio_id','shoot_date','shoot_time','customer_id','remarks'
    ];

    public function studio()
    {
        return $this->belongsTo(PhotoStudio::class,'photo_studio_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
