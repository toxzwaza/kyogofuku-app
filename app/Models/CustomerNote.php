<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerNote extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'customer_id',
        'content',
    ];

    /**
     * ユーザーとのリレーション
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * 顧客とのリレーション
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}
