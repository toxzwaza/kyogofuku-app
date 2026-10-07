<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    /**
     * 公開中かつ終了していないイベントLPのsitemap.xmlを返す。
     * テスト用イベント（タイトルに「テスト」を含む）は除外。
     */
    public function index()
    {
        $xml = Cache::remember('sitemap.xml', 3600, function () {
            $events = Event::query()
                ->where('is_public', true)
                ->where(function ($q) {
                    $q->whereNull('end_at')->orWhere('end_at', '>=', now()->startOfDay());
                })
                ->where('title', 'not like', '%テスト%')
                ->orderBy('id')
                ->get(['slug', 'updated_at']);

            $urls = $events->map(function ($e) {
                return '  <url><loc>' . e(url('/event/' . $e->slug)) . '</loc>'
                    . '<lastmod>' . $e->updated_at->toDateString() . '</lastmod></url>';
            })->implode("\n");

            return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
                . "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n"
                . $urls . "\n</urlset>\n";
        });

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }
}
