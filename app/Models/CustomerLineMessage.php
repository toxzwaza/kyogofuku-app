<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerLineMessage extends Model
{
    public const DIRECTION_INBOUND = 'inbound';

    public const DIRECTION_OUTBOUND = 'outbound';

    protected $fillable = [
        'customer_line_contact_id',
        'direction',
        'message_type',
        'text',
        'line_message_id',
        'payload',
        'sent_by_user_id',
        'admin_read_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'admin_read_at' => 'datetime',
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CustomerLineContact::class, 'customer_line_contact_id');
    }

    public function sentByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }
}
