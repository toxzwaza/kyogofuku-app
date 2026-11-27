<?php

namespace Tests\Feature\EventLp;

use App\Models\Event;
use App\Models\EventReservation;
use App\Models\Shop;
use App\Services\BladeLp\LineNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * hp改善-14〜16：イベント LP の索引制御・計測・ブランド表示。
 *
 * 外への通知（メール・LINE）は出さない：Mail::fake・Http::fake、LINE のトークンは空。
 */
class LpIndexingTrackingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Http::fake();
        Http::preventStrayRequests();
        config([
            'app.url' => 'https://example.com',
            'line.channel_token' => null,
            'line.group_id' => null,
            'services.company_gtm_id' => 'GTM-COMPANY1',
        ]);

        // Blade LP の LINE 通知は実行しない
        $this->app->bind(LineNotifier::class, function () {
            return new class {
                public function notify($event, $reservation): void {}
            };
        });
    }

    private function makeEvent(array $attrs = [], ?string $shopName = '岡山店'): Event
    {
        $event = Event::create(array_merge([
            'slug' => 'ev-'.uniqid(),
            'title' => '振袖展示会',
            'description' => '',
            'form_type' => 'reservation',
            'is_public' => true,
        ], $attrs));

        if ($shopName) {
            $shop = Shop::create(['name' => $shopName, 'is_active' => true]);
            $event->shops()->attach($shop->id);
        }

        return $event;
    }

    private function reservationPayload(array $extra = []): array
    {
        return array_merge([
            'name' => '山田 花子',
            'email' => 'hanako@example.com',
            'phone' => '090-1234-5678',
            'postal_code' => '700-0001',
            'privacy_agreed' => true,
        ], $extra);
    }

    // ---- A1・A2：索引 ----

    public function test_sitemap_excludes_contact_forms(): void
    {
        $this->makeEvent(['slug' => 'open-event']);
        $this->makeEvent(['slug' => 'contact-form', 'form_type' => 'contact']);

        $res = $this->get('/sitemap.xml')->assertOk();
        $res->assertSee('/event/open-event', false);
        $res->assertDontSee('/event/contact-form', false);
    }

    public function test_open_event_is_indexable(): void
    {
        $this->makeEvent(['slug' => 'open-event']);

        $this->get('/event/open-event')->assertOk()->assertDontSee('name="robots"', false);
    }

    public function test_contact_form_is_noindex_and_has_no_event_json_ld(): void
    {
        $this->makeEvent(['slug' => 'contact-form', 'form_type' => 'contact', 'title' => 'お問い合わせ']);

        $res = $this->get('/event/contact-form')->assertOk();
        $res->assertSee('<meta name="robots" content="noindex, follow">', false);
        $res->assertSee('"@type":"WebPage"', false);
        $res->assertDontSee('"@type":"Event"', false);
        $res->assertSee('へのお問い合わせフォームです。', false);
    }

    public function test_ended_event_is_noindex_and_links_to_store_reservation(): void
    {
        $this->makeEvent([
            'slug' => 'ended-event',
            'start_at' => now()->subDays(30)->toDateString(),
            'end_at' => now()->subDays(2)->toDateString(),
        ]);

        $this->get('/event/ended-event')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false)
            ->assertInertia(fn ($page) => $page
                ->where('isEnded', true)
                ->where('lpBrand.storeReservationUrl', 'https://example.com/event/reservation_store_kouichi')
                ->where('lpBrand.siteUrl', config('brands.brands.kouichi.site_url')));
    }

    public function test_reserve_page_is_noindex(): void
    {
        $this->makeEvent(['slug' => 'open-event', 'lp_design_slug' => 'pastel_fest']);

        $this->get('/event/open-event/reserve')
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex, follow">', false);
    }

    // ---- A3・A6・A10：ブランド・構造化データ・説明文 ----

    public function test_fukui_event_uses_hirata_brand_in_server_output(): void
    {
        $this->makeEvent(['slug' => 'fukui-event', 'title' => '福井の展示会'], '福井店');

        $res = $this->get('/event/fukui-event')->assertOk();
        $res->assertSee('<meta property="og:site_name" content="京呉服 平田">', false);
        $res->assertSee('福井の展示会｜京呉服 平田', false);
        $res->assertSee('主催: 京呉服 平田（福井の振袖専門店）', false);
        $res->assertDontSee('京呉服 好一', false);
        $res->assertInertia(fn ($page) => $page->where('lpBrand.key', 'hirata')->where('lpBrand.telDisplay', '0776-34-1529'));
    }

    public function test_store_reservation_page_has_webpage_json_ld_and_store_description(): void
    {
        $this->makeEvent(['slug' => 'reservation_store_kouichi', 'title' => '来店予約']);

        $res = $this->get('/event/reservation_store_kouichi')->assertOk();
        $res->assertSee('"@type":"WebPage"', false);
        $res->assertDontSee('"@type":"Event"', false);
        $res->assertSee('の来店予約ページです。', false);
    }

    public function test_og_image_falls_back_to_brand_logo(): void
    {
        $this->makeEvent(['slug' => 'no-thumb']);

        $this->get('/event/no-thumb')
            ->assertOk()
            ->assertSee('<meta property="og:image" content="https://example.com/storage/logo/logo_b.png">', false);
    }

    // ---- A9：過去の予約枠を props に載せない ----

    public function test_event_prop_does_not_include_all_timeslots(): void
    {
        $this->makeEvent(['slug' => 'open-event']);

        $this->get('/event/open-event')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->missing('event.timeslots'));
    }

    // ---- A4・A8：utm を予約まで運ぶ ----

    public function test_utm_is_kept_and_saved_with_medium_and_campaign(): void
    {
        $event = $this->makeEvent(['slug' => 'utm-event', 'lp_design_slug' => 'pastel_fest']);

        $this->get('/event/utm-event?utm_source=gbp&utm_medium=organic&utm_campaign=winter')->assertOk();
        // utm なしで開き直しても、前に覚えた流入元を消さない
        $this->get('/event/utm-event')->assertOk();
        $this->get('/event/utm-event/reserve')->assertOk();

        $this->post(route('event.reserve', $event->id), $this->reservationPayload())->assertRedirect();

        $reservation = EventReservation::where('event_id', $event->id)->firstOrFail();
        $this->assertSame('gbp', $reservation->utm_source);
        $this->assertSame('organic', $reservation->utm_medium);
        $this->assertSame('winter', $reservation->utm_campaign);
    }

    public function test_reservation_without_utm_is_saved_as_none(): void
    {
        $event = $this->makeEvent(['slug' => 'plain-event']);

        $this->get('/event/plain-event')->assertOk();
        $this->post(route('event.reserve', $event->id), $this->reservationPayload())->assertRedirect();

        $reservation = EventReservation::where('event_id', $event->id)->firstOrFail();
        $this->assertSame('NONE', $reservation->utm_source);
        $this->assertNull($reservation->utm_medium);
    }

    public function test_reserve_page_redirect_keeps_utm(): void
    {
        // LP デザインなし → 予約ページは LP へ戻す。そのとき utm を落とさない
        $this->makeEvent(['slug' => 'plain-reserve']);

        $this->get('/event/plain-reserve/reserve?utm_source=gbp&utm_medium=organic')
            ->assertRedirect('/event/plain-reserve?utm_source=gbp&utm_medium=organic');
    }

    // ---- A5：予約完了を dataLayer へ（1回だけ） ----

    public function test_success_page_gets_tracking_once(): void
    {
        $event = $this->makeEvent(['slug' => 'track-event']);

        $this->get('/event/track-event?utm_source=google&utm_medium=cpc&utm_campaign=c1')->assertOk();
        $this->post(route('event.reserve', $event->id), $this->reservationPayload())
            ->assertRedirect(route('event.reserve.success', $event->id));

        $reservation = EventReservation::where('event_id', $event->id)->firstOrFail();

        $this->get(route('event.reserve.success', $event->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('reservationTracking.event', 'reservation_complete')
                ->where('reservationTracking.reservation_id', $reservation->id)
                ->where('reservationTracking.brand', 'kouichi')
                ->where('reservationTracking.utm_source', 'google')
                ->where('reservationTracking.utm_medium', 'cpc')
                ->missing('reservationTracking.name')
                ->missing('reservationTracking.email')
                ->missing('reservationTracking.phone'));

        // 再読み込みでは計測しない
        $this->get(route('event.reserve.success', $event->id))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('reservationTracking', null));
    }

    // ---- A7：Blade LP に会社 GTM、代理店 GTM は残す ----

    public function test_blade_lp_has_company_gtm_and_keeps_agency_gtm(): void
    {
        $event = $this->makeEvent([
            'slug' => 'blade-shop-visit-hirata',
            'lp_design_slug' => 'shop_visit',
            'gtm_id' => 'GTM-AGENCY1',
            'title' => '来店予約',
        ], '福井店');

        $res = $this->get('/event/'.$event->slug)->assertOk();
        $res->assertSee("'GTM-COMPANY1'", false);
        $res->assertSee('GTM-AGENCY1', false);
        $res->assertSee('<meta property="og:site_name" content="京呉服 平田">', false);
        // 電話番号は config/brands.php から
        $res->assertSee('tel:0776341529', false);
        $res->assertSee(config('brands.brands.hirata.site_url'), false);
    }

    public function test_blade_thanks_page_pushes_reservation_complete_and_is_noindex(): void
    {
        $event = $this->makeEvent([
            'slug' => 'blade-shop-visit-kouichi',
            'lp_design_slug' => 'shop_visit',
            'form_schema' => [
                ['key' => 'name', 'type' => 'text', 'required' => true],
                ['key' => 'phone', 'type' => 'tel', 'required' => true],
            ],
        ]);

        $this->get('/event/'.$event->slug.'?utm_source=hp&utm_medium=referral')->assertOk();
        $this->post(route('blade-lp.reserve', $event->id), [
            'name' => '山田 花子',
            'phone' => '090-1234-5678',
        ])->assertRedirect();

        $reservation = EventReservation::where('event_id', $event->id)->firstOrFail();
        $this->assertSame('hp', $reservation->utm_source);
        $this->assertSame('referral', $reservation->utm_medium);

        $res = $this->get(route('event.reserve.success', $event->id))->assertOk();
        $res->assertSee('"event":"reservation_complete"', false);
        $res->assertSee('"utm_source":"hp"', false);
        $res->assertSee('<meta name="robots" content="noindex, follow">', false);
        $res->assertDontSee('090-1234-5678"', false);

        $this->get(route('event.reserve.success', $event->id))
            ->assertOk()
            ->assertDontSee('"event":"reservation_complete"', false);
    }

    // ---- 予約ページにも代理店 GTM ----

    public function test_reserve_page_outputs_agency_gtm(): void
    {
        $this->makeEvent(['slug' => 'agency-event', 'gtm_id' => 'GTM-AGENCY2', 'lp_design_slug' => 'pastel_fest']);

        $this->get('/event/agency-event/reserve')->assertOk()->assertSee('GTM-AGENCY2', false);
    }

    // ---- P1・P2：セキュリティヘッダー ----

    public function test_hsts_only_on_https_and_no_powered_by(): void
    {
        $this->makeEvent(['slug' => 'open-event']);

        $http = $this->get('http://example.com/event/open-event')->assertOk();
        $http->assertHeaderMissing('Strict-Transport-Security');
        $http->assertHeaderMissing('X-Powered-By');

        $https = $this->get('https://example.com/event/open-event')->assertOk();
        $https->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    }
}
