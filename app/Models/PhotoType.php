<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhotoType extends Model
{
    protected $fillable = ['name','code','description','sort_order','is_active'];

    public function customerPhotos()
    {
        return $this->hasMany(CustomerPhoto::class);
    }
}
