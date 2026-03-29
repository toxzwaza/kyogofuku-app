<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LineUnknownInboundMessage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'shop_id',
        'line_user_id',
        'text',
        'line_message_id',
        'raw_event',
        'created_at',
    ];

    protected $casts = [
        'raw_event' => 'array',
        'created_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }
}
