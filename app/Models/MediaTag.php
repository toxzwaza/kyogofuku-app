<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class MediaTag extends Model
{
    protected $fillable = [
        'parent_id',
        'name',
        'sort_order',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * 親タグ
     */
    public function parent()
    {
        return $this->belongsTo(MediaTag::class, 'parent_id');
    }

    /**
     * 子タグ
     */
    public function children()
    {
        return $this->hasMany(MediaTag::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    /**
     * このタグを付与されたメディア
     */
    public function mediaFiles()
    {
        return $this->belongsToMany(MediaFile::class, 'media_file_tag');
    }

    /**
     * 自分自身＋全子孫のID（絞り込みで子要素も含めるために使用）
     *
     * @param  Collection<int, MediaTag>|null  $all  事前ロード済みの全タグ（N+1回避用）
     * @return list<int>
     */
    public function selfAndDescendantIds(?Collection $all = null): array
    {
        $all = $all ?? static::query()->get(['id', 'parent_id']);
        $byParent = $all->groupBy('parent_id');

        $ids = [];
        $stack = [$this->id];
        while ($stack) {
            $current = array_pop($stack);
            $ids[] = $current;
            foreach ($byParent->get($current, collect()) as $child) {
                $stack[] = $child->id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * ルートからこのタグまでのパス名（例: "2026 > イベント > イベント1"）
     *
     * @param  Collection<int, MediaTag>|null  $all  事前ロード済みの全タグ（N+1回避用）
     */
    public function fullPath(?Collection $all = null, string $separator = ' > '): string
    {
        $all = $all ?? static::query()->get(['id', 'parent_id', 'name']);
        $byId = $all->keyBy('id');

        $names = [];
        $node = $byId->get($this->id) ?? $this;
        $guard = 0;
        while ($node && $guard++ < 100) {
            array_unshift($names, $node->name);
            $node = $node->parent_id ? $byId->get($node->parent_id) : null;
        }

        return implode($separator, $names);
    }
}
