<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DocumentImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'document_id',
        'image_path',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * ドキュメントとのリレーション
     */
    public function document()
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * 画像のURLを取得
     */
    public function getImageUrlAttribute()
    {
        if (!$this->image_path) {
            return null;
        }
        if (str_starts_with($this->image_path, 'http')) {
            return $this->image_path;
        }
        return asset('storage/' . $this->image_path);
    }
}

