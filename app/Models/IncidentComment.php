<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * 共有ノートの「その後の経過」。
 */
class IncidentComment extends Model
{
    protected $fillable = ['incident_id', 'user_id', 'body'];

    public function incident()
    {
        return $this->belongsTo(Incident::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
