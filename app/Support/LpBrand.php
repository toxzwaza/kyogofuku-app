<?php

namespace App\Support;

use App\Models\Event;
use Illuminate\Support\Str;

/**
 * イベント LP をどのブランド（好一／平田）で出すかを決め、表示用の値を返す。
 * 値の正本は config/brands.php。
 */
class LpBrand
{
    /**
     * @return array<string, mixed>
     */
    public static function forEvent(?Event $event): array
    {
        return self::get(self::keyForEvent($event));
    }

    public static function keyForEvent(?Event $event): string
    {
        if (!$event) {
            return config('brands.default', 'kouichi');
        }

        $keyword = (string) config('brands.hirata_shop_keyword', '福井');
        $shopNames = $event->relationLoaded('shops')
            ? $event->shops->pluck('name')
            : $event->shops()->withoutGlobalScope('visibleGroup')->pluck('name');

        $isHirata = $keyword !== '' && $shopNames->contains(fn ($name) => Str::contains((string) $name, $keyword));

        return $isHirata ? 'hirata' : config('brands.default', 'kouichi');
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(string $key): array
    {
        $brands = config('brands.brands', []);
        $brand = $brands[$key] ?? $brands[config('brands.default', 'kouichi')] ?? [];

        $tel = (string) ($brand['tel'] ?? '');
        $brand['tel_link'] = preg_replace('/[^0-9+]/', '', $tel);
        $brand['store_reservation_url'] = !empty($brand['store_reservation_slug'])
            ? rtrim(config('app.url'), '/').'/event/'.$brand['store_reservation_slug']
            : null;
        $brand['logo_url'] = !empty($brand['logo'])
            ? rtrim(config('app.url'), '/').$brand['logo']
            : null;

        return $brand;
    }

    /**
     * Vue に渡す値（公開してよい項目だけ）
     *
     * @return array<string, mixed>
     */
    public static function propsForEvent(?Event $event): array
    {
        $b = self::forEvent($event);

        return [
            'key' => $b['key'] ?? null,
            'name' => $b['name'] ?? null,
            'logo' => $b['logo'] ?? null,
            'tel' => $b['tel_link'] ?? null,
            'telDisplay' => $b['tel'] ?? null,
            'siteUrl' => $b['site_url'] ?? null,
            'shopUrl' => $b['shop_url'] ?? null,
            'rentalUrl' => $b['rental_url'] ?? null,
            'catalogUrl' => $b['catalog_url'] ?? null,
            'instagramUrl' => $b['instagram_url'] ?? null,
            'lineUrl' => $b['line_url'] ?? null,
            'storeReservationUrl' => $b['store_reservation_url'] ?? null,
        ];
    }

    /**
     * 通年の店舗来店予約ページか（slug が reservation_store で始まる）
     */
    public static function isStoreReservation(Event $event): bool
    {
        $prefix = (string) config('brands.store_reservation_slug_prefix', 'reservation_store');

        return $prefix !== '' && Str::startsWith((string) $event->slug, $prefix);
    }
}
