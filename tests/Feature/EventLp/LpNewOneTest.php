<?php

namespace Tests\Feature\EventLp;

use App\Models\Event;
use App\Models\EventImage;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * NEW ONE 最新ふりそで発表：前の画像（メディアライブラリ・alt「【福井】…」）を、
 * 店舗（好一／平田）ごとの「雑誌の表紙風」の画像に差し替える（config/lp_new_one.php）。
 *
 * 外への通知は出さない：Mail::fake・Http::fake。DB は使い捨てのテスト用。
 */
class LpNewOneTest extends TestCase
{
    use RefreshDatabase;

    private const LEGACY = [
        'b06efac4-a241-41b1-bd10-6d5fd0de160a.webp', // 【福井】中村
        '373e6d8a-f87a-48a3-b6e3-b9728472d3f6.webp', // 【福井】なつみみ
        '073ebce5-8eff-4d4c-8a8b-20e30606495e.webp', // 【福井】安斉
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::fake();
        Http::preventStrayRequests();
        config([
            'app.url' => 'https://example.com',
            'lp_new_one.base_url' => 'https://example.com/images/lp/new-one',
            // 前の画像（S3 public/media）の URL を作るだけ。S3 には接続しない
            'filesystems.disks.s3_public.region' => 'ap-northeast-1',
            'filesystems.disks.s3_public.bucket' => 'test-bucket',
            'filesystems.disks.s3_public.key' => 'test',
            'filesystems.disks.s3_public.secret' => 'test',
        ]);
    }

    /**
     * 画像 3 枚（通常）→ 前の NEW ONE 3 枚 → 画像 1 枚（通常）の並びのイベント
     */
    private function makeEvent(string $shopName = '岡山店', bool $withLegacy = true): Event
    {
        $event = Event::create([
            'slug' => 'ev-'.uniqid(),
            'title' => '振袖大祭典',
            'description' => '',
            'form_type' => 'reservation',
            'is_public' => true,
        ]);
        $shop = Shop::create(['name' => $shopName, 'is_active' => true]);
        $event->shops()->attach($shop->id);

        $sort = 1;
        foreach (['a', 'b', 'c'] as $name) {
            EventImage::create(['event_id' => $event->id, 'path' => "events/{$event->id}/{$name}.webp", 'storage_disk' => 'public', 'sort_order' => $sort++]);
        }
        if ($withLegacy) {
            foreach (self::LEGACY as $file) {
                EventImage::create(['event_id' => $event->id, 'path' => "media/{$file}", 'storage_disk' => 's3', 'alt' => '【福井】前の画像', 'sort_order' => $sort++]);
            }
        }
        EventImage::create(['event_id' => $event->id, 'path' => "events/{$event->id}/z.webp", 'storage_disk' => 'public', 'sort_order' => $sort++]);

        return $event;
    }

    public function test_kouichi_legacy_images_are_replaced_by_magazine_images_in_order(): void
    {
        $event = $this->makeEvent('岡山店');

        $this->get("/event/{$event->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Event/Show')
                ->has('images', 3 + 8 + 1)
                ->where('images.2.path', fn ($p) => str_ends_with($p, '/c.webp'))
                ->where('images.3.path', 'https://example.com/images/lp/new-one/kouichi/kokonoe-nakamura-risa.webp')
                ->where('images.3.alt', 'NEW ONE 最新ふりそで発表｜九重×中村里砂（京呉服 好一）')
                ->where('images.3.width', 1080)
                ->where('images.3.height', 1500)
                ->where('images.10.path', 'https://example.com/images/lp/new-one/kouichi/ourin-ranman.webp')
                ->where('images.11.path', fn ($p) => str_ends_with($p, '/z.webp'))
            );
    }

    public function test_no_fukui_alt_and_no_legacy_media_remain(): void
    {
        $event = $this->makeEvent('岡山店');

        $response = $this->get("/event/{$event->slug}")->assertOk();
        $images = $response->viewData('page')['props']['images'];
        foreach ($images as $image) {
            $this->assertStringNotContainsString('【福井】', (string) ($image['alt'] ?? ''));
            $this->assertStringNotContainsString('/media/', (string) $image['path']);
        }
    }

    public function test_hirata_gets_its_own_nine_images_including_allure(): void
    {
        $event = $this->makeEvent('福井店');

        $this->get("/event/{$event->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('images', 3 + 9 + 1)
                ->where('images.3.path', 'https://example.com/images/lp/new-one/hirata/kokonoe-nakamura-risa.webp')
                ->where('images.11.path', 'https://example.com/images/lp/new-one/hirata/allure.webp')
                ->where('images.11.alt', 'NEW ONE 最新ふりそで発表｜Allure（京呉服 平田）')
            );
    }

    public function test_event_without_legacy_images_is_unchanged(): void
    {
        $event = $this->makeEvent('岡山店', false);

        $this->get("/event/{$event->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('images', 4));
    }

    public function test_brand_can_be_turned_off_individually(): void
    {
        config(['lp_new_one.disabled' => ['kouichi:edel', 'kouichi:nuts']]);
        $event = $this->makeEvent('岡山店');

        $response = $this->get("/event/{$event->slug}")->assertOk();
        $paths = array_column($response->viewData('page')['props']['images'], 'path');
        $this->assertCount(3 + 6 + 1, $paths);
        $this->assertNotContains('https://example.com/images/lp/new-one/kouichi/edel.webp', $paths);
        $this->assertNotContains('https://example.com/images/lp/new-one/kouichi/nuts.webp', $paths);
        $this->assertContains('https://example.com/images/lp/new-one/kouichi/marune.webp', $paths);
    }

    public function test_item_enabled_false_in_config_hides_that_brand(): void
    {
        $items = config('lp_new_one.stores.hirata.items');
        foreach ($items as &$item) {
            if ($item['key'] === 'allure') {
                $item['enabled'] = false;
            }
        }
        unset($item);
        config(['lp_new_one.stores.hirata.items' => $items]);
        $event = $this->makeEvent('福井店');

        $response = $this->get("/event/{$event->slug}")->assertOk();
        $paths = array_column($response->viewData('page')['props']['images'], 'path');
        $this->assertCount(3 + 8 + 1, $paths);
        $this->assertNotContains('https://example.com/images/lp/new-one/hirata/allure.webp', $paths);
    }

    public function test_store_all_off_keeps_legacy_images(): void
    {
        config(['lp_new_one.disabled' => ['kouichi:*']]);
        $event = $this->makeEvent('岡山店');

        $response = $this->get("/event/{$event->slug}")->assertOk();
        $paths = array_column($response->viewData('page')['props']['images'], 'path');
        $this->assertCount(7, $paths);
        $this->assertStringEndsWith('/media/'.self::LEGACY[0], $paths[3]);
    }

    public function test_feature_off_keeps_legacy_images(): void
    {
        config(['lp_new_one.enabled' => false]);
        $event = $this->makeEvent('福井店');

        $this->get("/event/{$event->slug}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->has('images', 7));
    }

    public function test_slideshow_and_cta_positions_follow_the_replacement(): void
    {
        $event = $this->makeEvent('岡山店');
        $slideshowId = DB::table('slideshows')->insertGetId([
            'name' => 'テスト', 'type' => 'fade', 'created_at' => now(), 'updated_at' => now(),
        ]);
        // 2枚目の後ろ（NEW ONE の前）と、前の NEW ONE の後ろ（= 6 枚目の後ろ）
        foreach ([2, 6] as $position) {
            DB::table('event_slideshow_positions')->insert([
                'event_id' => $event->id, 'slideshow_id' => $slideshowId, 'position' => $position, 'sort_order' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        foreach ([3, 6, 7] as $position) {
            DB::table('event_cta_button_positions')->insert([
                'event_id' => $event->id, 'position' => $position, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $props = $this->get("/event/{$event->slug}")->assertOk()->viewData('page')['props'];

        // 3枚 + 新しい 8 枚 = 11 枚目の後ろ、が前の「6 枚目の後ろ」にあたる
        $this->assertSame([2, 11], array_map('intval', array_keys($props['slideshowPositions'])));
        $this->assertSame([3, 11, 12], $props['ctaButtonPositions']);
    }
}
