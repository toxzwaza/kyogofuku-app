<?php

namespace Tests\Feature\Referral;

use App\Models\Contract;
use App\Models\Customer;
use App\Models\CustomerLineContact;
use App\Models\Event;
use App\Models\EventReservation;
use App\Models\Plan;
use App\Models\Referral;
use App\Models\ReferralCode;
use App\Models\Shop;
use App\Services\Referral\ReferralPointService;
use Database\Seeders\ReferralSettingsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * LIFFエンドポイント。IDトークン検証(LINE verify API)は Http::fake でモックする。
 */
class ReferralLiffTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferralSettingsSeeder::class);
        $this->shop = Shop::create(['name' => '店', 'is_active' => true]);
        config(['line.liff.login_channel_id' => '2009633621']);
        config(['line.referral.liff_url' => 'https://liff.line.me/2009633621-Wh0yxjfu']);
    }

    /** 成約済（確定contract）顧客を作る。ContractObserver が紹介コードを発行する。 */
    private function contractedCustomer(string $name = '紹介者'): Customer
    {
        $plan = Plan::firstOrCreate(['code' => 'PTEST'], ['name' => '振袖', 'is_active' => true]);
        $c = $this->customer($name);
        Contract::create([
            'customer_id' => $c->id,
            'shop_id' => $this->shop->id,
            'plan_id' => $plan->id,
            'contract_date' => today(),
            'kimono_type' => '振袖',
            'total_amount' => 200000,
            'status' => '確定',
        ]);

        return $c;
    }

    /** verify と push をモック。verify は sub を返す。 */
    private function fakeLine(string $sub): void
    {
        Http::fake([
            '*oauth2/v2.1/verify*' => Http::response(['sub' => $sub], 200),
            '*api.line.me/v2/bot/message/push*' => Http::response([], 200),
        ]);
    }

    private function customer(string $name = '顧客'): Customer
    {
        return Customer::create(['name' => $name, 'shop_id' => $this->shop->id]);
    }

    private function link(Customer $c, string $lineUserId): void
    {
        CustomerLineContact::create([
            'customer_id' => $c->id,
            'shop_id' => $this->shop->id,
            'line_user_id' => $lineUserId,
            'label' => '本人',
        ]);
    }

    /** 指定店舗で開催中の公開イベントを作る */
    private function ongoingEvent(Shop $shop, string $title = 'イベント', array $attrs = []): Event
    {
        $event = Event::create(array_merge([
            'slug' => 'ev-'.uniqid('', true),
            'title' => $title,
            'form_type' => 'reservation',
            'is_public' => true,
            'start_at' => today()->subDay(),
            'end_at' => today()->addDay(),
            'thumbnail_path' => 'events/thumb.png',
        ], $attrs));
        $event->shops()->attach($shop->id);

        return $event;
    }

    /** 未連携の被紹介者（referrals レコードのみ）を作る */
    private function referredButNotLinked(string $lineUserId, Customer $referrer): void
    {
        Referral::create([
            'referrer_customer_id' => $referrer->id,
            'referred_line_user_id' => $lineUserId,
            'status' => Referral::STATUS_LINKED,
        ]);
    }

    /** イベント予約経由の連携（customer_id なし・event_reservation_id あり）を作る */
    private function linkReservation(string $lineUserId): void
    {
        $event = Event::create([
            'slug' => 't-'.uniqid('', true),
            'title' => 'E',
            'form_type' => 'reservation',
        ]);
        $reservation = EventReservation::create([
            'event_id' => $event->id,
            'name' => '予約者',
            'email' => 'r@example.com',
            'phone' => '000',
        ]);
        CustomerLineContact::create([
            'customer_id' => null,
            'event_reservation_id' => $reservation->id,
            'shop_id' => $this->shop->id,
            'line_user_id' => $lineUserId,
            'label' => '本人',
        ]);
    }

    public function test_referral_link_creates_referral_and_pushes(): void
    {
        $this->fakeLine('Ufriend');
        $referrer = $this->customer('紹介者');
        $code = ReferralCode::create(['customer_id' => $referrer->id, 'code' => 'ABC12345']);

        $this->postJson(route('line.liff.referral.link'), ['id_token' => 'tok', 'ref' => $code->code])
            ->assertOk()
            ->assertJson(['state' => 'linked']);

        $this->assertDatabaseHas('referrals', [
            'referrer_customer_id' => $referrer->id,
            'referred_line_user_id' => 'Ufriend',
            'status' => 'linked',
        ]);
        // お礼テキストPushが送られた
        Http::assertSent(fn ($req) => str_contains($req->url(), '/v2/bot/message/push'));
    }

    public function test_referral_link_unauthorized_without_valid_token(): void
    {
        Http::fake(['*oauth2/v2.1/verify*' => Http::response(['error' => 'invalid'], 400)]);
        $referrer = $this->customer('紹介者');
        $code = ReferralCode::create(['customer_id' => $referrer->id, 'code' => 'ABC12345']);

        $this->postJson(route('line.liff.referral.link'), ['id_token' => 'bad', 'ref' => $code->code])
            ->assertStatus(401);
    }

    public function test_my_points_data_for_linked_customer(): void
    {
        $this->fakeLine('Ume');
        $customer = $this->customer('本人');
        $this->link($customer, 'Ume');
        app(ReferralPointService::class)->applyDelta($customer, 5000, 'adjust');

        $res = $this->postJson(route('line.liff.my-points.data'), ['id_token' => 'tok'])
            ->assertOk()
            ->assertJson(['state' => 'ok', 'balance' => 5000])
            // 還元率（ブロンズ=3%）と次ステージ（シルバー：あと4件）
            ->assertJsonPath('reward_rate', 3)
            ->assertJsonPath('next_stage.stage', 'silver')
            ->assertJsonPath('next_stage.remaining', 4);
        // ステージ章は data URI（ngrok等でも確実に表示するため外部URLにしない）
        $this->assertStringStartsWith('data:image/png;base64,', (string) $res->json('stage_badge'));
        $res->assertJsonStructure(['coupons']);
    }

    public function test_my_points_data_not_linked(): void
    {
        $this->fakeLine('Unobody');

        $this->postJson(route('line.liff.my-points.data'), ['id_token' => 'tok'])
            ->assertStatus(403)
            ->assertJson(['state' => 'not_linked']);
    }

    public function test_me_returns_code_for_contracted_customer(): void
    {
        $this->fakeLine('Uref1');
        $customer = $this->contractedCustomer();
        $this->link($customer, 'Uref1');

        $res = $this->postJson(route('line.liff.referral.me'), ['id_token' => 'tok'])
            ->assertOk()
            ->assertJson(['state' => 'ok']);

        $code = $res->json('code');
        $this->assertNotEmpty($code);
        $this->assertStringContainsString('?ref='.$code, $res->json('share_url'));
        // ContractObserver が成約時にコードを発行している
        $this->assertDatabaseHas('referral_codes', ['customer_id' => $customer->id, 'code' => $code]);
    }

    public function test_me_not_eligible_without_contract(): void
    {
        $this->fakeLine('Uref2');
        $customer = $this->customer('未成約');
        $this->link($customer, 'Uref2');

        $this->postJson(route('line.liff.referral.me'), ['id_token' => 'tok'])
            ->assertOk()
            ->assertJson(['state' => 'not_eligible']);
        $this->assertDatabaseMissing('referral_codes', ['customer_id' => $customer->id]);
    }

    public function test_me_not_linked(): void
    {
        $this->fakeLine('Unobody2');

        $this->postJson(route('line.liff.referral.me'), ['id_token' => 'tok'])
            ->assertStatus(403)
            ->assertJson(['state' => 'not_linked']);
    }

    public function test_my_points_not_linked_includes_referrer_shop_ongoing_events(): void
    {
        $this->fakeLine('Urefevents');
        $referrer = $this->customer('紹介者');
        $this->referredButNotLinked('Urefevents', $referrer);

        $target = $this->ongoingEvent($this->shop, '開催中イベント');
        // 除外されるもの：他店舗・非公開・終了済み
        $otherShop = Shop::create(['name' => '他店', 'is_active' => true]);
        $this->ongoingEvent($otherShop, '他店舗イベント');
        $this->ongoingEvent($this->shop, '非公開イベント', ['is_public' => false]);
        $this->ongoingEvent($this->shop, '終了イベント', ['end_at' => today()->subDay()]);

        $res = $this->postJson(route('line.liff.my-points.data'), ['id_token' => 'tok'])
            ->assertStatus(403)
            ->assertJson(['state' => 'not_linked'])
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.title', '開催中イベント');

        $this->assertStringContainsString('/event/'.$target->slug.'/reserve', (string) $res->json('events.0.reserve_url'));
        $this->assertNotEmpty($res->json('events.0.thumbnail_url'));
    }

    public function test_my_points_not_linked_events_empty_without_referral(): void
    {
        $this->fakeLine('Unoreferral');
        $this->ongoingEvent($this->shop, '開催中イベント');

        $this->postJson(route('line.liff.my-points.data'), ['id_token' => 'tok'])
            ->assertStatus(403)
            ->assertJson(['state' => 'not_linked', 'events' => []]);
    }

    public function test_check_ready_includes_events_for_ref_code(): void
    {
        $this->fakeLine('Ufriendevents');
        $referrer = $this->customer('紹介者');
        $code = ReferralCode::create(['customer_id' => $referrer->id, 'code' => 'EVENT123']);
        $this->ongoingEvent($this->shop, '開催中イベント');

        $this->postJson(route('line.liff.referral.check'), ['id_token' => 'tok', 'ref' => $code->code])
            ->assertOk()
            ->assertJson(['state' => 'ready'])
            ->assertJsonCount(1, 'events')
            ->assertJsonPath('events.0.title', '開催中イベント');
    }

    public function test_my_points_data_not_contracted_for_reservation_linked_user(): void
    {
        $this->fakeLine('Uresvpts');
        $this->linkReservation('Uresvpts');

        $this->postJson(route('line.liff.my-points.data'), ['id_token' => 'tok'])
            ->assertStatus(403)
            ->assertJson(['state' => 'not_contracted']);
    }

    public function test_mypage_data_not_contracted_for_reservation_linked_user(): void
    {
        $this->fakeLine('Uresvmyp');
        $this->linkReservation('Uresvmyp');

        $this->postJson(route('line.liff.mypage.data'), ['id_token' => 'tok'])
            ->assertStatus(403)
            ->assertJson(['state' => 'not_contracted']);
    }

    public function test_me_not_contracted_for_reservation_linked_user(): void
    {
        $this->fakeLine('Uresvme');
        $this->linkReservation('Uresvme');

        $this->postJson(route('line.liff.referral.me'), ['id_token' => 'tok'])
            ->assertStatus(403)
            ->assertJson(['state' => 'not_contracted']);
    }

    public function test_unlink_removes_contact(): void
    {
        $this->fakeLine('Uunlink');
        $customer = $this->customer('解除する人');
        $this->link($customer, 'Uunlink');
        $this->assertDatabaseHas('customer_line_contacts', ['line_user_id' => 'Uunlink']);

        $this->postJson(route('line.liff.unlink'), ['id_token' => 'tok'])
            ->assertOk()
            ->assertJson(['state' => 'unlinked']);

        $this->assertDatabaseMissing('customer_line_contacts', ['line_user_id' => 'Uunlink']);
    }

    public function test_unlink_unauthorized_without_valid_token(): void
    {
        Http::fake(['*oauth2/v2.1/verify*' => Http::response(['error' => 'invalid'], 400)]);

        $this->postJson(route('line.liff.unlink'), ['id_token' => 'bad'])
            ->assertStatus(401);
    }

    public function test_mypage_data_returns_customer_info(): void
    {
        $this->fakeLine('Ump');
        // 成約あり顧客（contract_date が文字列でも format で落ちないことを保証）
        $customer = $this->contractedCustomer('山田花子');
        $this->link($customer, 'Ump');

        $this->postJson(route('line.liff.mypage.data'), ['id_token' => 'tok'])
            ->assertOk()
            ->assertJson(['state' => 'ok', 'customer' => ['name' => '山田花子']])
            ->assertJsonPath('contracts.0.contract_date', today()->format('Y-m-d'))
            ->assertJsonPath('contracts.0.total_amount', 200000);
    }

    public function test_link_push_contains_referrer_id_and_name(): void
    {
        $this->fakeLine('Ufriend');
        $referrer = $this->customer('紹介者花子');
        ReferralCode::create(['customer_id' => $referrer->id, 'code' => 'ABC12345']);

        $this->postJson(route('line.liff.referral.link'), ['id_token' => 'tok', 'ref' => 'ABC12345'])
            ->assertOk()
            ->assertJson(['state' => 'linked']);

        // お礼Pushの本文に「[顧客ID]氏名さんから友達紹介されました！」が含まれる
        Http::assertSent(function ($req) use ($referrer) {
            if (!str_contains($req->url(), '/v2/bot/message/push')) {
                return false;
            }
            $text = $req->data()['messages'][0]['text'] ?? '';

            return str_contains($text, "[{$referrer->id}]紹介者花子さんから友達紹介されました");
        });
    }

    public function test_me_returns_referrer_for_referred_user(): void
    {
        // 被紹介者（まだ顧客ではない・linked状態）でも、誰から紹介されたかを返す
        $referrer = $this->customer('紹介者太郎');
        Referral::create([
            'referrer_customer_id' => $referrer->id,
            'referred_line_user_id' => 'Ufriend2',
            'status' => Referral::STATUS_LINKED,
            'expires_at' => now()->addMonths(6),
        ]);
        $this->fakeLine('Ufriend2');

        $this->postJson(route('line.liff.referral.me'), ['id_token' => 'tok'])
            ->assertStatus(403)
            ->assertJson(['state' => 'not_linked'])
            ->assertJsonPath('referrer.id', $referrer->id)
            ->assertJsonPath('referrer.name', '紹介者太郎');
    }

    public function test_me_referrer_null_without_referral(): void
    {
        $this->fakeLine('Ulonely');

        $this->postJson(route('line.liff.referral.me'), ['id_token' => 'tok'])
            ->assertStatus(403)
            ->assertJson(['state' => 'not_linked', 'referrer' => null]);
    }
}
