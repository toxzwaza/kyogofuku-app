<?php

namespace App\Http\Controllers\Concerns;

use App\Models\MediaTag;
use Illuminate\Http\Request;

/**
 * 「タブレット画像」メディアタグ（iPadアップロード画像）の解決。
 *
 * 顧客詳細・予約詳細の写真セクションで、メディアライブラリから
 * 自店舗プレフィックス（HIRATA-/KOUICHI-）の画像だけを選択できるようにする。
 */
trait ResolvesTabletMediaTags
{
    protected static string $tabletMediaTag = 'タブレット画像';

    protected function tabletTagPrefixForUser(Request $request): string
    {
        $mainShop = $request->user()?->shops()
            ->where('shops.is_active', true)
            ->orderByDesc('shop_user.main')
            ->orderBy('shops.id')
            ->first();

        return $mainShop?->name === '福井店' ? 'HIRATA-' : 'KOUICHI-';
    }

    /**
     * タブレット画像の配下タグのうち、ログインユーザーの店舗プレフィックスに一致するもの。
     *
     * @return \Illuminate\Support\Collection<int, MediaTag>
     */
    protected function tabletDeviceTagsForUser(Request $request)
    {
        $parent = MediaTag::query()
            ->whereNull('parent_id')
            ->where('name', static::$tabletMediaTag)
            ->first();
        if (! $parent) {
            return collect();
        }

        return $parent->children()
            ->where('name', 'like', $this->tabletTagPrefixForUser($request).'%')
            ->orderBy('name')
            ->get(['id', 'name', 'parent_id']);
    }
}
