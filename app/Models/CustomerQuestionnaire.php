<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerQuestionnaire extends Model
{
    protected $fillable = [
        'customer_id',
        'event_reservation_id',
        'page1_photo_id',
        'page2_photo_id',
        'placements',
        'page1_placements',
        'composed_page2_path',
        'composed_page1_path',
    ];

    protected $casts = [
        'placements' => 'array',
        'page1_placements' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /** 予約紐付けのアンケート（顧客未紐付けの予約で登録されたもの） */
    public function eventReservation()
    {
        return $this->belongsTo(EventReservation::class);
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
