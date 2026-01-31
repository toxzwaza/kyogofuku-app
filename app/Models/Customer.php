<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'name', 'kana', 'guardian_name', 'guardian_name_kana', 'birth_date', 'coming_of_age_year',
        'ceremony_area_id', 'phone_number', 'postal_code', 'address',
        'email', 'has_visited_before', 'referred_by_name', 'school_name', 'staff_name',
        'visit_reasons', 'parking_usage', 'parking_car_count', 'considering_plans', 'heard_from', 'inquiry_message',
        'remarks',
    ];

    protected $casts = [
        'has_visited_before' => 'boolean',
        'visit_reasons' => 'array',
        'considering_plans' => 'array',
        'parking_car_count' => 'integer',
    ];

    public function ceremonyArea()
    {
        return $this->belongsTo(CeremonyArea::class);
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class);
    }

    public function tags()
    {
        return $this->belongsToMany(
            CustomerTag::class,
            'customer_tag_assignments'
        )->withPivot('note')->withTimestamps();
    }

    public function photos()
    {
        return $this->hasMany(CustomerPhoto::class);
    }

    public function photoSlots()
    {
        return $this->hasMany(PhotoSlot::class);
    }

    /**
     * イベント予約とのリレーション（参加イベント）
     */
    public function eventReservations()
    {
        return $this->hasMany(EventReservation::class);
    }

    /**
     * 顧客メモとのリレーション
     */
    public function notes()
    {
        return $this->hasMany(CustomerNote::class);
    }
}
