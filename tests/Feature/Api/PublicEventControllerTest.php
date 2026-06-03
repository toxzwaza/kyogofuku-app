<?php

namespace Tests\Feature\Api;

use App\Models\Event;
use App\Models\EventImage;
use App\Models\Shop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublicEventControllerTest extends TestCase
{
    use RefreshDatabase;

    private Shop $okayama;
    private Shop $joto;
    private Shop $hama;
    private Shop $fukui;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->okayama = Shop::create(['name' => '岡山店', 'is_active' => true]);
        $this->joto = Shop::create(['name' => '城東店', 'is_active' => true]);
        $this->hama = Shop::create(['name' => '浜店', 'is_active' => true]);
        $this->fukui = Shop::create(['name' => '福井店', 'is_active' => true]);
    }

    private function createEvent(array $attributes = [], array $shopIds = []): Event
    {
        $event = Event::create(array_merge([
            'slug' => 'test-' . uniqid(),
            'title' => 'テストイベント',
            'form_type' => 'reservation',
            'is_public' => true,
        ], $attributes));

        if (!empty($shopIds)) {
            $event->shops()->attach($shopIds);
        }

        return $event;
    }

    public function test_index_returns_events_for_single_shop(): void
    {
        $okayamaEvent = $this->createEvent(['slug' => 'okayama-1', 'end_at' => now()->addDays(5)], [$this->okayama->id]);
        $fukuiEvent = $this->createEvent(['slug' => 'fukui-1', 'end_at' => now()->addDays(3)], [$this->fukui->id]);

        $res = $this->getJson('/api/public/events?shop=' . urlencode('岡山店'));

        $res->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'okayama-1');
    }

    public function test_index_returns_events_for_multiple_shops_without_duplicates(): void
    {
        $sharedEvent = $this->createEvent(
            ['slug' => 'shared', 'end_at' => now()->addDays(2)],
            [$this->okayama->id, $this->joto->id]
        );
        $okayamaOnly = $this->createEvent(
            ['slug' => 'okayama-only', 'end_at' => now()->addDays(4)],
            [$this->okayama->id]
        );
        $fukuiOnly = $this->createEvent(
            ['slug' => 'fukui-only', 'end_at' => now()->addDays(1)],
            [$this->fukui->id]
        );

        $res = $this->getJson('/api/public/events?shop=' . urlencode('岡山店,城東店,浜店'));

        $res->assertOk()
            ->assertJsonCount(2, 'data');

        $slugs = collect($res->json('data'))->pluck('slug')->all();
        $this->assertContains('shared', $slugs);
        $this->assertContains('okayama-only', $slugs);
        $this->assertNotContains('fukui-only', $slugs);

        $sharedPayload = collect($res->json('data'))->firstWhere('slug', 'shared');
        $this->assertEqualsCanonicalizing(['岡山店', '城東店'], $sharedPayload['shop_names']);
    }

    public function test_index_excludes_non_public_events(): void
    {
        $this->createEvent(['slug' => 'public', 'is_public' => true, 'end_at' => now()->addDays(2)], [$this->okayama->id]);
        $this->createEvent(['slug' => 'private', 'is_public' => false, 'end_at' => now()->addDays(1)], [$this->okayama->id]);

        $res = $this->getJson('/api/public/events?shop=' . urlencode('岡山店'));

        $res->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', 'public');
    }

    public function test_index_orders_by_end_at_ascending_with_nulls_at_end(): void
    {
        $this->createEvent(['slug' => 'null-end', 'end_at' => null], [$this->okayama->id]);
        $this->createEvent(['slug' => 'late', 'end_at' => now()->addDays(10)], [$this->okayama->id]);
        $this->createEvent(['slug' => 'early', 'end_at' => now()->addDays(2)], [$this->okayama->id]);

        $res = $this->getJson('/api/public/events?shop=' . urlencode('岡山店'));

        $slugs = collect($res->json('data'))->pluck('slug')->all();
        $this->assertSame(['early', 'late', 'null-end'], $slugs);
    }

    public function test_index_includes_only_reservation_form_types(): void
    {
        $this->createEvent(['slug' => 'fr',  'form_type' => 'reservation',         'end_at' => now()->addDays(3)], [$this->okayama->id]);
        $this->createEvent(['slug' => 'hk',  'form_type' => 'reservation_hakama',  'end_at' => now()->addDays(4)], [$this->okayama->id]);
        $this->createEvent(['slug' => 'doc', 'form_type' => 'document',            'end_at' => now()->addDays(2)], [$this->okayama->id]);
        $this->createEvent(['slug' => 'ct',  'form_type' => 'contact',             'end_at' => now()->addDays(1)], [$this->okayama->id]);

        $res = $this->getJson('/api/public/events?shop=' . urlencode('岡山店'));

        $slugs = collect($res->json('data'))->pluck('slug')->all();
        $this->assertEqualsCanonicalizing(['fr', 'hk'], $slugs);
    }

    public function test_footer_banner_includes_only_reservation_form_types(): void
    {
        // document の方が end_at が近いが、reservation/reservation_hakama 以外は除外されるので hakama が選ばれる
        $this->createEvent(['slug' => 'doc-soon', 'form_type' => 'document',           'end_at' => now()->addDays(1)], [$this->okayama->id]);
        $this->createEvent(['slug' => 'hk-far',   'form_type' => 'reservation_hakama', 'end_at' => now()->addDays(5)], [$this->okayama->id]);

        $res = $this->getJson('/api/public/events/footer-banner?shop=' . urlencode('岡山店'));
        $res->assertOk()->assertJsonPath('data.slug', 'hk-far');
    }

    public function test_index_excludes_past_end_at_events(): void
    {
        $this->createEvent(['slug' => 'past-1', 'end_at' => now()->subDays(1)], [$this->okayama->id]);
        $this->createEvent(['slug' => 'past-30', 'end_at' => now()->subDays(30)], [$this->okayama->id]);
        $this->createEvent(['slug' => 'today', 'end_at' => now()], [$this->okayama->id]);
        $this->createEvent(['slug' => 'future', 'end_at' => now()->addDays(5)], [$this->okayama->id]);
        $this->createEvent(['slug' => 'no-end', 'end_at' => null], [$this->okayama->id]);

        $res = $this->getJson('/api/public/events?shop=' . urlencode('岡山店'));

        $slugs = collect($res->json('data'))->pluck('slug')->all();
        $this->assertSame(['today', 'future', 'no-end'], $slugs);
        $this->assertNotContains('past-1', $slugs);
        $this->assertNotContains('past-30', $slugs);
    }

    public function test_index_respects_limit_parameter(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $this->createEvent(['slug' => "evt-$i", 'end_at' => now()->addDays($i)], [$this->okayama->id]);
        }

        $res = $this->getJson('/api/public/events?shop=' . urlencode('岡山店') . '&limit=3');

        $res->assertOk()->assertJsonCount(3, 'data');
    }

    public function test_index_limit_is_capped_at_20(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->createEvent(['slug' => "evt-$i", 'end_at' => now()->addDays($i)], [$this->okayama->id]);
        }

        $res = $this->getJson('/api/public/events?shop=' . urlencode('岡山店') . '&limit=100');

        $res->assertOk()->assertJsonCount(20, 'data');
    }

    public function test_index_returns_thumbnail_first_image_lp_url(): void
    {
        $event = $this->createEvent([
            'slug' => 'rich',
            'end_at' => now()->addDays(3),
            'thumbnail_path' => 'thumb/test.webp',
            'thumbnail_storage_disk' => 'public',
        ], [$this->okayama->id]);

        EventImage::create([
            'event_id' => $event->id,
            'path' => 'images/first.jpg',
            'storage_disk' => 'public',
            'sort_order' => 0,
        ]);
        EventImage::create([
            'event_id' => $event->id,
            'path' => 'images/second.jpg',
            'storage_disk' => 'public',
            'sort_order' => 1,
        ]);

        $res = $this->getJson('/api/public/events?shop=' . urlencode('岡山店'));

        $res->assertOk()
            ->assertJsonPath('data.0.slug', 'rich')
            ->assertJsonPath('data.0.lp_url', config('app.event_lp_base_url') . '/event/rich');

        $this->assertStringContainsString('thumb/test.webp', $res->json('data.0.thumbnail_url'));
        $this->assertStringContainsString('images/first.jpg', $res->json('data.0.first_image_url'));
    }

    public function test_index_first_image_is_null_when_no_images(): void
    {
        $this->createEvent(['slug' => 'no-img', 'end_at' => now()->addDays(2)], [$this->okayama->id]);

        $res = $this->getJson('/api/public/events?shop=' . urlencode('岡山店'));

        $res->assertJsonPath('data.0.first_image_url', null);
    }

    public function test_index_returns_empty_when_shop_missing(): void
    {
        $this->createEvent(['slug' => 'x', 'end_at' => now()->addDays(1)], [$this->okayama->id]);

        $res = $this->getJson('/api/public/events');

        $res->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_footer_banner_returns_closest_upcoming_event(): void
    {
        $this->createEvent(['slug' => 'far', 'end_at' => now()->addDays(20)], [$this->okayama->id]);
        $this->createEvent(['slug' => 'near', 'end_at' => now()->addDays(2)], [$this->okayama->id]);
        $this->createEvent(['slug' => 'mid', 'end_at' => now()->addDays(7)], [$this->okayama->id]);

        $res = $this->getJson('/api/public/events/footer-banner?shop=' . urlencode('岡山店'));

        $res->assertOk()->assertJsonPath('data.slug', 'near');
    }

    public function test_footer_banner_excludes_past_end_dates(): void
    {
        $this->createEvent(['slug' => 'past', 'end_at' => now()->subDays(1)], [$this->okayama->id]);

        $res = $this->getJson('/api/public/events/footer-banner?shop=' . urlencode('岡山店'));

        $res->assertNoContent();
    }

    public function test_footer_banner_falls_back_to_null_end_at_when_no_dated_events(): void
    {
        // end_at NULL のみ存在 → それを 1件返す
        $this->createEvent(['slug' => 'null-end', 'end_at' => null], [$this->okayama->id]);

        $res = $this->getJson('/api/public/events/footer-banner?shop=' . urlencode('岡山店'));

        $res->assertOk()->assertJsonPath('data.slug', 'null-end');
    }

    public function test_footer_banner_prefers_dated_over_null(): void
    {
        // end_at NULL と end_at 日付付きが両方存在 → 日付付きが優先
        $this->createEvent(['slug' => 'no-end', 'end_at' => null], [$this->okayama->id]);
        $this->createEvent(['slug' => 'has-end', 'end_at' => now()->addDays(10)], [$this->okayama->id]);

        $res = $this->getJson('/api/public/events/footer-banner?shop=' . urlencode('岡山店'));

        $res->assertOk()->assertJsonPath('data.slug', 'has-end');
    }

    public function test_footer_banner_works_with_multiple_shops(): void
    {
        $this->createEvent(['slug' => 'fukui', 'end_at' => now()->addDays(1)], [$this->fukui->id]);
        $this->createEvent(['slug' => 'joto', 'end_at' => now()->addDays(5)], [$this->joto->id]);

        $res = $this->getJson('/api/public/events/footer-banner?shop=' . urlencode('岡山店,城東店,浜店'));

        $res->assertOk()->assertJsonPath('data.slug', 'joto');
    }

    public function test_footer_banner_no_data_returns_204(): void
    {
        $res = $this->getJson('/api/public/events/footer-banner?shop=' . urlencode('岡山店'));
        $res->assertNoContent();
    }

    public function test_cache_invalidated_when_event_saved(): void
    {
        $event = $this->createEvent(['slug' => 'cache1', 'end_at' => now()->addDays(2)], [$this->okayama->id]);

        $this->getJson('/api/public/events?shop=' . urlencode('岡山店'))
            ->assertOk()
            ->assertJsonPath('data.0.title', 'テストイベント');

        $event->update(['title' => '更新されたタイトル']);

        $this->getJson('/api/public/events?shop=' . urlencode('岡山店'))
            ->assertOk()
            ->assertJsonPath('data.0.title', '更新されたタイトル');
    }
}
