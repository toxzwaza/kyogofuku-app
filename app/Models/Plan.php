<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = ['name','code','description','base_price','is_active'];

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }
}
