<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhotoSlot extends Model
{
    protected $fillable = [
        'photo_studio_id','shoot_date','shoot_time','customer_id','remarks',
        'assignment_label','user_id','plan_id'
    ];

    public function studio()
    {
        return $this->belongsTo(PhotoStudio::class,'photo_studio_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * 担当店舗との多対多リレーション
     */
    public function shops()
    {
        return $this->belongsToMany(Shop::class, 'photo_slot_shop');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function plan()
    {
        return $this->belongsTo(Plan::class);
    }
}
