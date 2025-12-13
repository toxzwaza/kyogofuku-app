<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'name','kana','guardian_name','birth_date','coming_of_age_year',
        'ceremony_area_id','phone_number','postal_code','address','remarks'
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
}
