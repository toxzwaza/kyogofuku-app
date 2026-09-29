<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ケア提案。オーバービューの「今日のケアリスト」に表示する1件を表す。
 * バッチ（care:generate）で生成される。
 */
class CareSuggestion extends Model
{
    protected $fillable = [
        'care_type',
        'subject_type',
        'event_reservation_id',
        'customer_id',
        'shop_id',
        'assignee',
        'subject_name',
        'care_score',
        'priority_band',
        'score_factors',
        'reservation_status',
        'days_since_contact',
        'prospect_label',
        'seijin_year',
        'status_summary',
        'next_action',
        'next_action_type',
        'last_contact_at',
        'ai_generated',
        'generated_at',
    ];

    protected $casts = [
        'score_factors' => 'array',
        'ai_generated' => 'boolean',
        'last_contact_at' => 'datetime',
        'generated_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(EventReservation::class, 'event_reservation_id');
    }
}
