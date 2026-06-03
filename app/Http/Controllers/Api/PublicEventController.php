<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class PublicEventController extends Controller
{
    public const CACHE_TTL = 300; // 5分
    public const CACHE_VERSION_KEY = 'public_events:version';
    private const MAX_LIMIT = 20;
    private const DEFAULT_LIMIT = 10;

    /** 外部HP連携で対象とするフォーム種別（振袖予約・袴予約のみ） */
    private const ALLOWED_FORM_TYPES = ['reservation', 'reservation_hakama'];

    /**
     * Pick Up 用：公開中イベント一覧
     * GET /api/public/events?shop=岡山店,城東店,浜店&limit=10
     */
    public function index(Request $request): JsonResponse
    {
        $shops = $this->parseShops($request->input('shop'));
        $limit = $this->parseLimit($request->input('limit'));

        if (empty($shops)) {
            return response()->json(['data' => []]);
        }

        $cacheKey = $this->cacheKey('events_index', $shops, $limit);

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($shops, $limit) {
            $today = now()->toDateString();
            $events = Event::with(['shops', 'images'])
                ->where('is_public', true)
                ->whereIn('form_type', self::ALLOWED_FORM_TYPES)
                ->whereHas('shops', fn ($q) => $q->whereIn('shops.name', $shops))
                // end_at が過去のイベント（受付終了済み）は除外。end_at NULL（期限なし）は表示
                ->where(function ($q) use ($today) {
                    $q->whereNull('end_at')
                      ->orWhereDate('end_at', '>=', $today);
                })
                ->orderByRaw('CASE WHEN end_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('end_at', 'asc')
                ->orderBy('id', 'asc')
                ->limit($limit)
                ->get();

            return $events->map(fn ($e) => $this->transform($e))->values()->all();
        });

        return response()->json(['data' => $data]);
    }

    /**
     * footer バナー用：受付終了日が最も近い1件
     * GET /api/public/events/footer-banner?shop=岡山店,城東店,浜店
     */
    public function footerBanner(Request $request)
    {
        $shops = $this->parseShops($request->input('shop'));

        if (empty($shops)) {
            return response()->noContent();
        }

        $cacheKey = $this->cacheKey('events_footer', $shops);

        $data = Cache::remember($cacheKey, self::CACHE_TTL, function () use ($shops) {
            $event = Event::with(['shops', 'images'])
                ->where('is_public', true)
                ->whereIn('form_type', self::ALLOWED_FORM_TYPES)
                ->whereHas('shops', fn ($q) => $q->whereIn('shops.name', $shops))
                ->whereNotNull('end_at')
                ->whereDate('end_at', '>=', now()->toDateString())
                ->orderBy('end_at', 'asc')
                ->orderBy('id', 'asc')
                ->first();

            return $event ? $this->transform($event) : null;
        });

        if ($data === null) {
            return response()->noContent();
        }

        return response()->json(['data' => $data]);
    }

    /**
     * イベントを公開API用に整形
     */
    private function transform(Event $event): array
    {
        $firstImage = $event->images->first();
        $firstImageUrl = null;
        if ($firstImage) {
            $firstImageUrl = $firstImage->webp_url ?? $firstImage->url;
        }

        $baseUrl = rtrim(config('app.event_lp_base_url', 'https://kyogofuku-event.com'), '/');

        return [
            'id' => $event->id,
            'slug' => $event->slug,
            'title' => $event->title,
            'description' => $event->description,
            'shop_names' => $event->shops->pluck('name')->values()->all(),
            'start_at' => optional($event->start_at)->toDateString(),
            'end_at' => optional($event->end_at)->toDateString(),
            'thumbnail_url' => $event->thumbnail_url,
            'first_image_url' => $firstImageUrl,
            'lp_url' => $baseUrl . '/event/' . $event->slug,
        ];
    }

    /**
     * shop パラメータをパース（カンマ区切り、空白除去、空要素除外）
     */
    private function parseShops(?string $raw): array
    {
        if (!$raw) {
            return [];
        }
        $shops = array_map('trim', explode(',', $raw));
        return array_values(array_unique(array_filter($shops, fn ($v) => $v !== '')));
    }

    /**
     * limit パラメータをパース（1〜MAX_LIMIT に制限）
     */
    private function parseLimit($raw): int
    {
        $limit = is_numeric($raw) ? (int) $raw : self::DEFAULT_LIMIT;
        return max(1, min($limit, self::MAX_LIMIT));
    }

    /**
     * キャッシュキー生成（shops はソートして正規化、世代番号を付与）
     */
    private function cacheKey(string $prefix, array $shops, ?int $limit = null): string
    {
        sort($shops);
        $version = Cache::rememberForever(self::CACHE_VERSION_KEY, fn () => 1);
        $key = 'public_events:v' . $version . ':' . $prefix . ':shops=' . implode('|', $shops);
        if ($limit !== null) {
            $key .= ':limit=' . $limit;
        }
        return $key;
    }
}
