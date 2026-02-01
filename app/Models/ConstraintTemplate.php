<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConstraintTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'body',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function shops()
    {
        return $this->belongsToMany(Shop::class, 'constraint_template_shop');
    }

    public function customerConstraints()
    {
        return $this->hasMany(CustomerConstraint::class);
    }
}
