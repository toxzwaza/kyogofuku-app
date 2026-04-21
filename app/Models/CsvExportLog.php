<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CsvExportLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'target',
        'filters',
        'columns',
        'row_count',
        'file_name',
    ];

    protected $casts = [
        'filters' => 'array',
        'columns' => 'array',
        'row_count' => 'integer',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
