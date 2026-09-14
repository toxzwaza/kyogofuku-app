<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LineUnknownInboundMessage extends Model
{
    /** Webhook が follow / unfollow イベントの追跡用に保存する text 値 */
    public const FOLLOW_EVENT_TEXTS = ['(follow event)', '(unfollow event)'];

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

    /** follow / unfollow イベントの追跡記録を除外する（text=NULL のメッセージ記録は残す） */
    public function scopeWithoutFollowEvents(Builder $query): Builder
    {
        return $query->where(function (Builder $q) {
            $q->whereNull('text')
                ->orWhereNotIn('text', self::FOLLOW_EVENT_TEXTS);
        });
    }
}
