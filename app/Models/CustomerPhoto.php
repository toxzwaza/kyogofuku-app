<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerPhoto extends Model
{
    protected $fillable = ['customer_id','photo_type_id','file_path','remarks'];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function type()
    {
        return $this->belongsTo(PhotoType::class,'photo_type_id');
    }
}
