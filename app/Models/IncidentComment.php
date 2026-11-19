<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 共有ノートの記録（対応記録・決定事項・状態の変更）。旧称「経過」。
 *
 * - 入力者（user_id）と時刻（created_at）はサーバーだけが入れる。画面から送られた値は使わない
 * - 消せない。直せるのは本人が当日だけで、直す前の本文は revisions に残す
 * - 状態の変更（type=status）は自動で残すもので、直せない
 */
class IncidentComment extends Model
{
    public const TYPE_RECORD = 'record';
    public const TYPE_DECISION = 'decision';
    public const TYPE_STATUS = 'status';

    public const TYPES = [
        self::TYPE_RECORD   => '対応記録',
        self::TYPE_DECISION => '決定事項',
        self::TYPE_STATUS   => '状態の変更',
    ];

    protected $fillable = ['body'];

    protected $attributes = [
        'type' => self::TYPE_RECORD,
    ];

    protected $casts = [
        'edited_at' => 'datetime',
    ];

    public function incident()
    {
        return $this->belongsTo(Incident::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function revisions()
    {
        return $this->hasMany(IncidentCommentRevision::class)->orderBy('created_at')->orderBy('id');
    }

    /** 本人が当日（日本時間）だけ直せる。状態の変更は直せない */
    public function editableBy(User $user): bool
    {
        return $this->type !== self::TYPE_STATUS
            && (int) $this->user_id === (int) $user->id
            && $this->created_at !== null
            && $this->created_at->copy()->timezone(config('app.timezone'))->isSameDay(now());
    }
}
