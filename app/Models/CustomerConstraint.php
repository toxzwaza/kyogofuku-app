<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerConstraint extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'constraint_template_id',
        'signed_at',
        'signature_image',
        'explainer_user_id',
        'check_values',
    ];

    protected $casts = [
        'signed_at' => 'date',
        'check_values' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function constraintTemplate()
    {
        return $this->belongsTo(ConstraintTemplate::class);
    }

    public function explainerUser()
    {
        return $this->belongsTo(User::class, 'explainer_user_id');
    }
}
