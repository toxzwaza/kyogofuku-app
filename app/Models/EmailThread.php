<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailThread extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_reservation_id',
        'subject',
    ];

    /**
     * Get the event reservation that owns the thread.
     */
    public function eventReservation(): BelongsTo
    {
        return $this->belongsTo(EventReservation::class);
    }

    /**
     * Get the emails for the thread.
     */
    public function emails(): HasMany
    {
        return $this->hasMany(Email::class)->orderBy('created_at', 'asc');
    }
}
