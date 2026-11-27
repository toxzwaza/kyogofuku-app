<?php

namespace Tests\Feature\EventLp;

use App\Models\Event;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * hp改善-18：旧 LP の URL → 今の LP へ 301 で転送する（イベントの「URLエイリアス」）。
 *
 * 例：浜店の旧 LP（非公開にしたイベントの slug）を、今の浜店の店舗予約 LP の別URLに登録する。
 * 外への通知（メール・LINE）は出さない：Mail::fake・Http::fake。予約は送らない。
 */
class LpSlugRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::fake();
        Http::preventStrayRequests();
    }

    private function makeEvent(array $attrs = []): Event
    {
        return Event::create(array_merge([
            'slug' => 'ev-'.uniqid(),
            'title' => '店舗予約',
            'description' => '',
            'form_type' => 'reservation',
            'is_public' => true,
        ], $attrs));
    }

    /** 今の浜店 LP と、非公開になった旧 LP 2つ（本番の形に合わせたもの） */
    private function hamaEvents(): Event
    {
        $this->makeEvent(['slug' => 'hakama-rental-2025', 'title' => '卒業袴・二尺袖のレンタル（旧）', 'is_public' => false]);
        $this->makeEvent(['slug' => 'reservation_iah6KIRiHv', 'title' => '京呉服好一 袴専門店（旧）', 'is_public' => false]);

        return $this->makeEvent([
            'slug' => 'reservation_store_hama',
            'title' => '京呉服好一 店舗予約 【浜店】',
            'form_type' => 'reservation_hakama',
            'slug_aliases' => ['reservation_iah6KIRiHv', 'hakama-rental-2025'],
        ]);
    }

    public function test_old_lp_url_redirects_permanently_to_new_lp(): void
    {
        $this->hamaEvents();

        $this->get('/event/reservation_iah6KIRiHv')
            ->assertStatus(301)
            ->assertRedirect('/event/reservation_store_hama');

        $this->get('/event/hakama-rental-2025')
            ->assertStatus(301)
            ->assertRedirect('/event/reservation_store_hama');
    }

    public function test_redirect_keeps_utm_and_other_query(): void
    {
        $this->hamaEvents();

        $res = $this->get('/event/reservation_iah6KIRiHv?utm_source=gbp&utm_medium=organic&utm_campaign=hama&line_ref=abc')
            ->assertStatus(301);

        $location = $res->headers->get('Location');
        $this->assertSame('/event/reservation_store_hama', parse_url($location, PHP_URL_PATH));
        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame([
            'utm_source' => 'gbp',
            'utm_medium' => 'organic',
            'utm_campaign' => 'hama',
            'line_ref' => 'abc',
        ], $query);
    }

    public function test_utm_is_captured_on_new_lp_after_redirect(): void
    {
        $hama = $this->hamaEvents();

        $location = $this->get('/event/hakama-rental-2025?utm_source=flyer&utm_medium=qr')
            ->assertStatus(301)
            ->headers->get('Location');

        $this->get($location)->assertOk();

        // PR #16 の流入元の記録（UtmTracker）が新しい LP のイベントで働く
        $this->assertSame('flyer', session("event_utm.{$hama->id}")['utm_source'] ?? null);
    }

    public function test_reserve_page_of_old_lp_redirects_to_new_reserve_page(): void
    {
        $this->hamaEvents();

        $this->get('/event/reservation_iah6KIRiHv/reserve?utm_source=ad')
            ->assertStatus(301)
            ->assertRedirect('/event/reservation_store_hama/reserve?utm_source=ad');
    }

    public function test_new_lp_itself_is_not_redirected(): void
    {
        $this->hamaEvents();

        $this->get('/event/reservation_store_hama')->assertOk();
    }

    public function test_unknown_slug_is_still_404(): void
    {
        $this->hamaEvents();

        $this->get('/event/no-such-lp')->assertNotFound();
    }

    public function test_alias_is_ignored_when_target_event_is_not_public(): void
    {
        $this->makeEvent(['slug' => 'new-lp', 'is_public' => false, 'slug_aliases' => ['old-lp']]);

        $this->get('/event/old-lp')->assertNotFound();
    }

    public function test_public_event_slug_wins_over_alias(): void
    {
        $this->makeEvent(['slug' => 'shared-slug', 'title' => '公開中']);
        $this->makeEvent(['slug' => 'other-lp', 'slug_aliases' => ['shared-slug']]);

        $this->get('/event/shared-slug')->assertOk();
    }

    // ---- 管理画面：旧 → 新 を登録できる ----

    private function admin(): User
    {
        $user = User::factory()->create(['attendance_role' => 'manager']);
        $shop = Shop::create(['name' => 'テスト店', 'is_active' => true]);
        $user->shops()->attach($shop->id);

        return $user;
    }

    private function updatePayload(Event $event, array $aliases): array
    {
        return [
            'title' => $event->title,
            'slug' => $event->slug,
            'form_type' => $event->form_type,
            'is_public' => true,
            'slug_aliases' => $aliases,
        ];
    }

    public function test_admin_can_register_slug_of_non_public_event_as_alias(): void
    {
        $user = $this->admin();
        $old = $this->makeEvent(['slug' => 'old-hama-lp', 'is_public' => false]);
        $new = $this->makeEvent(['slug' => 'new-hama-lp']);

        $this->actingAs($user)
            ->put(route('admin.events.update', $new), $this->updatePayload($new, ['old-hama-lp']))
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertSame(['old-hama-lp'], $new->refresh()->slug_aliases);
        // 旧イベントのデータはそのまま（消さない・変えない）
        $this->assertSame('old-hama-lp', $old->refresh()->slug);

        $this->get('/event/old-hama-lp?utm_source=gbp')
            ->assertStatus(301)
            ->assertRedirect('/event/new-hama-lp?utm_source=gbp');
    }

    public function test_admin_cannot_register_slug_of_public_event_as_alias(): void
    {
        $user = $this->admin();
        $this->makeEvent(['slug' => 'live-lp']);
        $new = $this->makeEvent(['slug' => 'new-lp2']);

        $this->actingAs($user)
            ->put(route('admin.events.update', $new), $this->updatePayload($new, ['live-lp']))
            ->assertSessionHasErrors('slug_aliases.0');

        $this->assertNull($new->refresh()->slug_aliases);
    }
}
