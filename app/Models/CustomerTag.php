<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerTag extends Model
{
    protected $fillable = ['name','slug','description','color','is_active'];

    public function customers()
    {
        return $this->belongsToMany(
            Customer::class,
            'customer_tag_assignments'
        )->withPivot('note')->withTimestamps();
    }
}
