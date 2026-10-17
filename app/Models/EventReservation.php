<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventReservation extends Model
{
    use HasFactory;

    /** 流入経路（utm_source）が空の予約をまとめる表示ラベル */
    public const UTM_SOURCE_UNKNOWN_LABEL = '（直接・不明）';

    protected $fillable = [
        'event_id',
        'document_id',
        'name',
        'email',
        'phone',
        'status',
        'status_updated_by_user_id',
        'request_method',
        'postal_code',
        'venue_id',
        'has_visited_before',
        'address',
        'birth_date',
        'seijin_year',
        'referred_by_name',
        'line_referral_id',
        'reservation_datetime',
        'furigana',
        'school_name',
        'koichi_furisode_used',
        'graduation_ceremony_year',
        'graduation_ceremony_month',
        'graduation_ceremony_date',
        'visitor_count',
        'companion_types',
        'companion_hakama_usage',
        'staff_name',
        'admin_assignee',
        'entrance_ticket_send_status',
        'visit_reasons',
        'parking_usage',
        'parking_car_count',
        'considering_plans',
        'heard_from',
        'inquiry_message',
        'privacy_agreed',
        'cancel_flg',
        'customer_id',
        'utm_source',
        'form_data',
    ];

    protected $casts = [
        'has_visited_before' => 'boolean',
        'birth_date' => 'date',
        'seijin_year' => 'integer',
        'koichi_furisode_used' => 'boolean',
        'graduation_ceremony_year' => 'integer',
        'graduation_ceremony_month' => 'integer',
        'graduation_ceremony_date' => 'date',
        'visitor_count' => 'integer',
        'companion_types' => 'array',
        'companion_hakama_usage' => 'boolean',
        'parking_car_count' => 'integer',
        'considering_plans' => 'array',
        'visit_reasons' => 'array',
        'privacy_agreed' => 'boolean',
        'cancel_flg' => 'boolean',
        'form_data' => 'array',
    ];

    /**
     * 顧客とのリレーション
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * 予約紐付けの写真（顧客未紐付けの間のみ。顧客紐付け時に顧客側へ移行される）
     */
    public function photos()
    {
        return $this->hasMany(CustomerPhoto::class, 'event_reservation_id');
    }

    /**
     * 予約紐付けの振袖アンケート（顧客未紐付けの間のみ）
     */
    public function questionnaire()
    {
        return $this->hasOne(CustomerQuestionnaire::class, 'event_reservation_id');
    }

    /**
     * イベントとのリレーション
     */
    /**
     * LINE友達紹介経由の予約 → 紹介レコード
     */
    public function lineReferral()
    {
        return $this->belongsTo(\App\Models\Referral::class, 'line_referral_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * 会場とのリレーション
     */
    public function venue()
    {
        return $this->belongsTo(Venue::class);
    }

    /**
     * メモとのリレーション
     */
    public function notes()
    {
        return $this->hasMany(ReservationNote::class, 'event_reservation_id');
    }

    /**
     * ステータス更新者とのリレーション
     */
    public function statusUpdatedBy()
    {
        return $this->belongsTo(User::class, 'status_updated_by_user_id');
    }

    /**
     * スケジュールとのリレーション
     */
    public function schedule()
    {
        return $this->hasOne(StaffSchedule::class, 'event_reservation_id');
    }

    /**
     * 流入経路（utm_source）別の予約者数を集計する。
     *
     * オーバービュー（直近30日・所属店舗）とイベント詳細（イベント単位）で共通利用する。
     * 期間・イベント等の絞り込みは呼び出し側が $query に適用し、ここでは
     * 「キャンセル除く」「空の utm_source は『（直接・不明）』にまとめる」「件数の多い順」を統一する。
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation  $query
     * @return array<int, array{source: string, count: int}>
     */
    public static function utmSourceDistribution($query): array
    {
        return $query
            ->where('cancel_flg', false)
            ->selectRaw("COALESCE(NULLIF(utm_source, ''), ?) as source, COUNT(*) as cnt", [self::UTM_SOURCE_UNKNOWN_LABEL])
            ->groupBy('source')
            ->orderByDesc('cnt')
            ->get()
            ->map(fn ($r) => ['source' => (string) $r->source, 'count' => (int) $r->cnt])
            ->values()
            ->all();
    }

    /**
     * この予約の流入経路ラベル（utmSourceDistribution の source と同じ値になる）
     */
    public function utmSourceLabel(): string
    {
        return ($this->utm_source !== null && $this->utm_source !== '')
            ? (string) $this->utm_source
            : self::UTM_SOURCE_UNKNOWN_LABEL;
    }

    /**
     * メールとのリレーション
     */
    public function emails()
    {
        return $this->hasMany(Email::class);
    }

    /**
     * メールスレッドとのリレーション
     */
    public function emailThreads()
    {
        return $this->hasMany(EmailThread::class);
    }

    /**
     * 資料とのリレーション
     */
    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * UTMトラッキングとのリレーション
     */
    public function utmTracking()
    {
        return $this->hasOne(EventUtmTracking::class);
    }

    /**
     * 未顧客のときの LINE 連絡先（1 予約 1 件想定）
     */
    public function lineContacts()
    {
        return $this->hasMany(CustomerLineContact::class, 'event_reservation_id');
    }
}
