<?php

namespace App\Models\Meibo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** ラベル発行のテンプレート（福井発送・岡山発送など）。名前は重ならない */
class MeiboLabelTemplate extends Model
{
    use HasFactory;

    protected $table = 'meibo_label_templates';

    protected $fillable = ['name', 'sender_zip', 'sender_address', 'sender_company', 'addressee', 'with_family', 'tantou'];

    protected $casts = [
        'with_family' => 'boolean',
    ];
}
