<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Staudenmeir\EloquentEagerLimit\HasEagerLimit;

class CustomerPhoto extends Model
{
    use HasEagerLimit;
    protected $fillable = ['customer_id', 'event_reservation_id', 'photo_type_id', 'file_path', 'storage_disk', 'remarks'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /** 予約紐付けの写真（顧客未紐付けの予約で撮影・登録されたもの） */
    public function eventReservation()
    {
        return $this->belongsTo(EventReservation::class);
    }

    public function type()
    {
        return $this->belongsTo(PhotoType::class,'photo_type_id');
    }
}
