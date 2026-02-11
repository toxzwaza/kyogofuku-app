<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class S3TestItem extends Model
{
    protected $fillable = [
        'path',
        'visibility_type',
        'original_name',
    ];
}
