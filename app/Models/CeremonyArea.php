<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CeremonyArea extends Model
{
    protected $fillable = ['name','furi','prefecture','remarks'];

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }
}
