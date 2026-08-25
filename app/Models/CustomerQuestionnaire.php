<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerQuestionnaire extends Model
{
    protected $fillable = [
        'customer_id',
        'page1_photo_id',
        'page2_photo_id',
        'placements',
        'composed_page2_path',
    ];

    protected $casts = [
        'placements' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function page1Photo()
    {
        return $this->belongsTo(CustomerPhoto::class, 'page1_photo_id');
    }

    public function page2Photo()
    {
        return $this->belongsTo(CustomerPhoto::class, 'page2_photo_id');
    }
}
