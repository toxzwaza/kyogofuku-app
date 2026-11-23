<?php

namespace App\Models\Meibo;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/** 郵便番号の座標（名簿ダッシュボードの地図用） */
class MeiboZipGeo extends Model
{
    use HasFactory;

    protected $table = 'meibo_zip_geo';

    protected $primaryKey = 'zip';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['zip', 'pref', 'lat', 'lng', 'src'];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
    ];
}
