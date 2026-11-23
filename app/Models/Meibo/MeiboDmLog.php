<?php

namespace App\Models\Meibo;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** 名簿の DM 状態・備考の変更履歴（変わったときだけ 1 行。誰が・いつ） */
class MeiboDmLog extends Model
{
    use HasFactory;

    protected $table = 'meibo_dm_logs';

    public $timestamps = false;

    protected $fillable = ['person_id', 'old_status', 'new_status', 'old_note', 'note', 'changed_by_user_id', 'changed_at'];

    protected $casts = [
        'changed_at' => 'datetime',
    ];

    public function person()
    {
        return $this->belongsTo(MeiboPerson::class, 'person_id');
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }
}
