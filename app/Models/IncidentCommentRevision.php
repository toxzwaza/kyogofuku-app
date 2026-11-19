<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 共有ノートの記録を直したときの、直す前の本文。
 */
class IncidentCommentRevision extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [];

    public function comment()
    {
        return $this->belongsTo(IncidentComment::class, 'incident_comment_id');
    }

    public function editor()
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
