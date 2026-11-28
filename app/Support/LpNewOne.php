<?php

namespace App\Support;

use App\Models\Event;

/**
 * イベント LP の「NEW ONE 最新ふりそで発表」画像（雑誌の表紙風）。
 * 設定の正本は config/lp_new_one.php。
 *
 * イベント画像の中の前の NEW ONE 画像（メディアライブラリ）を、店舗（好一／平田）ごとの
 * 新しい画像に差し替える。差し替えは公開ページの props の上だけで行い、DB は変えない。
 */
class LpNewOne
{
    /**
     * 店舗に出す画像の一覧（オンのブランドだけ、設定の順）
     *
     * @return list<array{key: string, name: string, src: string, alt: string, width: int, height: int}>
     */
    public static function itemsForStore(string $storeKey): array
    {
        if (!config('lp_new_one.enabled', true)) {
            return [];
        }

        $store = config("lp_new_one.stores.{$storeKey}");
        if (!is_array($store)) {
            return [];
        }

        $disabled = (array) config('lp_new_one.disabled', []);
        if (in_array("{$storeKey}:*", $disabled, true)) {
            return [];
        }

        $baseUrl = rtrim((string) (config('lp_new_one.base_url') ?: asset('images/lp/new-one')), '/');
        $altPrefix = (string) config('lp_new_one.alt_prefix', 'NEW ONE 最新ふりそで発表');
        $storeLabel = (string) ($store['label'] ?? '');
        $width = (int) config('lp_new_one.width', 1080);
        $height = (int) config('lp_new_one.height', 1500);

        $items = [];
        foreach ((array) ($store['items'] ?? []) as $item) {
            $key = (string) ($item['key'] ?? '');
            if ($key === '' || !($item['enabled'] ?? false) || in_array("{$storeKey}:{$key}", $disabled, true)) {
                continue;
            }
            $name = (string) ($item['name'] ?? $key);
            $items[] = [
                'key' => $key,
                'name' => $name,
                'src' => "{$baseUrl}/{$storeKey}/{$key}.webp",
                'alt' => $altPrefix.'｜'.$name.($storeLabel !== '' ? "（{$storeLabel}）" : ''),
                'width' => $width,
                'height' => $height,
            ];
        }

        return $items;
    }

    /**
     * 画像のパスまたは URL が、前の NEW ONE 画像（メディアライブラリ）か
     */
    public static function isLegacyImage(?string $pathOrUrl): bool
    {
        if ($pathOrUrl === null || $pathOrUrl === '') {
            return false;
        }

        $path = str_replace('\\', '/', (string) (parse_url($pathOrUrl, PHP_URL_PATH) ?? $pathOrUrl));
        if (!str_contains('/'.ltrim($path, '/'), '/media/')) {
            return false;
        }

        return in_array(basename($path), (array) config('lp_new_one.legacy_media_files', []), true);
    }

    /**
     * 公開ページの images・スライドショーの位置・CTA の位置を受け取り、
     * 前の NEW ONE 画像を新しい画像に差し替えたものを返す。
     *
     * 位置（slideshowPositions のキー・ctaButtonPositions の値）は「何枚目の画像の後ろか」なので、
     * 画像の枚数が変わる分を付け直す（前の NEW ONE 画像の前後の並びは変えない）。
     *
     * @param  list<array<string, mixed>>  $images  EventPublicPageService が作る画像（'path' は URL）
     * @param  array<string|int, list<array<string, mixed>>>  $slideshowPositions
     * @param  list<int>  $ctaButtonPositions
     * @return array{images: list<array<string, mixed>>, slideshowPositions: array<string|int, list<array<string, mixed>>>, ctaButtonPositions: list<int>}
     */
    public static function apply(Event $event, array $images, array $slideshowPositions, array $ctaButtonPositions): array
    {
        $unchanged = [
            'images' => array_values($images),
            'slideshowPositions' => $slideshowPositions,
            'ctaButtonPositions' => $ctaButtonPositions,
        ];

        $images = array_values($images);
        $legacy = array_map(fn ($image) => self::isLegacyImage($image['path'] ?? null), $images);
        if (!in_array(true, $legacy, true)) {
            return $unchanged;
        }

        $items = self::itemsForStore(LpBrand::keyForEvent($event));
        if ($items === []) {
            return $unchanged;
        }

        $newImages = [];
        // $positionMap[n] = 前の並びで n 枚目の後ろ → 新しい並びで何枚目の後ろか
        $positionMap = [0 => 0];
        $inserted = false;
        foreach ($images as $i => $image) {
            if ($legacy[$i]) {
                if (!$inserted) {
                    foreach ($items as $item) {
                        $newImages[] = [
                            'id' => "new-one-{$item['key']}",
                            'path' => $item['src'],
                            'webp_path' => null,
                            'alt' => $item['alt'],
                            'sort_order' => $image['sort_order'] ?? null,
                            'margin_top_px' => null,
                            'margin_bottom_px' => null,
                            'width' => $item['width'],
                            'height' => $item['height'],
                            'new_one' => true,
                        ];
                    }
                    $inserted = true;
                }
            } else {
                $newImages[] = $image;
            }
            $positionMap[$i + 1] = count($newImages);
        }

        $newSlideshowPositions = [];
        foreach ($slideshowPositions as $position => $list) {
            $mapped = $positionMap[(int) $position] ?? (int) $position;
            $newSlideshowPositions[$mapped] = array_merge($newSlideshowPositions[$mapped] ?? [], (array) $list);
        }
        ksort($newSlideshowPositions);

        $newCta = [];
        foreach ($ctaButtonPositions as $position) {
            $newCta[] = $positionMap[(int) $position] ?? (int) $position;
        }
        $newCta = array_values(array_unique($newCta));
        sort($newCta);

        return [
            'images' => $newImages,
            'slideshowPositions' => $newSlideshowPositions,
            'ctaButtonPositions' => $newCta,
        ];
    }
}
