<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LineBroadcastRecipient extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'line_broadcast_id',
        'customer_line_contact_id',
        'line_user_id',
        'recipient_name',
        'recipient_kind',
        'status',
        'error_message',
        'sent_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(LineBroadcast::class, 'line_broadcast_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(CustomerLineContact::class, 'customer_line_contact_id');
    }
}
