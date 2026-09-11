<?php

namespace Tests\Feature\Line;

use App\Models\CustomerLineContact;
use App\Models\LineBroadcast;
use App\Models\LineBroadcastRecipient;
use App\Models\Shop;
use App\Services\Line\LineBroadcastSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class LineBroadcastSenderTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();
        Config::set('line.messaging.channel_access_token', 'test-token');
        $this->shop = Shop::create(['name' => 'テスト店舗', 'is_active' => true]);
    }

    private function makeContact(string $lineUserId): CustomerLineContact
    {
        return CustomerLineContact::create([
            'shop_id' => $this->shop->id,
            'line_user_id' => $lineUserId,
            'label' => 'テスト '.$lineUserId,
        ]);
    }

    private function makeBroadcast(): LineBroadcast
    {
        return LineBroadcast::create([
            'title' => 'テスト広告',
            'text' => 'キャンペーンのお知らせ',
            'status' => LineBroadcast::STATUS_DRAFT,
        ]);
    }

    public function test_multicast_send_records_recipients_and_dedupes_same_user(): void
    {
        Http::fake(['api.line.me/*' => Http::response([], 200)]);

        $broadcast = $this->makeBroadcast();
        $c1 = $this->makeContact('U0001');
        $c2 = $this->makeContact('U0002');

        // 同一連絡先を重複して渡しても 1 通に名寄せされる
        $result = app(LineBroadcastSender::class)->send($broadcast, collect([$c1, $c2, $c1]));

        $this->assertSame(2, $result['sent']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame(1, $result['skipped_duplicate']);
        $this->assertSame(0, $result['skipped_already_sent']);

        $this->assertSame(2, LineBroadcastRecipient::where('line_broadcast_id', $broadcast->id)
            ->where('status', LineBroadcastRecipient::STATUS_SENT)->count());
        $this->assertSame(LineBroadcast::STATUS_SENT, $broadcast->fresh()->status);

        // multicast が 1 回だけ呼ばれ、宛先が2件であること
        Http::assertSent(function ($request) {
            return str_contains($request->url(), '/multicast')
                && count($request['to']) === 2;
        });
    }

    public function test_second_send_skips_already_sent_users(): void
    {
        Http::fake(['api.line.me/*' => Http::response([], 200)]);

        $broadcast = $this->makeBroadcast();
        $c1 = $this->makeContact('U0001');
        $c2 = $this->makeContact('U0002');

        app(LineBroadcastSender::class)->send($broadcast, collect([$c1]));

        // 2回目: c1 は送信済みスキップ、c2 のみ送信される
        $result = app(LineBroadcastSender::class)->send($broadcast, collect([$c1, $c2]));

        $this->assertSame(1, $result['sent']);
        $this->assertSame(1, $result['skipped_already_sent']);

        // c1 のレコードは1件のまま（DB unique 制約含め二重登録されない）
        $this->assertSame(1, LineBroadcastRecipient::where('line_broadcast_id', $broadcast->id)
            ->where('line_user_id', 'U0001')->count());

        // 2回目の multicast の宛先に U0001 が含まれない
        $multicastCalls = collect(Http::recorded())
            ->filter(fn ($pair) => str_contains($pair[0]->url(), '/multicast'));
        $this->assertCount(2, $multicastCalls);
        $secondCall = $multicastCalls->values()[1][0];
        $this->assertSame(['U0002'], $secondCall['to']);
    }

    public function test_multicast_failure_falls_back_to_per_user_push(): void
    {
        Http::fake([
            'api.line.me/v2/bot/message/multicast' => Http::response('error', 500),
            'api.line.me/v2/bot/message/push' => Http::response([], 200),
        ]);

        $broadcast = $this->makeBroadcast();
        $c1 = $this->makeContact('U0001');
        $c2 = $this->makeContact('U0002');

        $result = app(LineBroadcastSender::class)->send($broadcast, collect([$c1, $c2]));

        $this->assertSame(2, $result['sent']);
        $this->assertSame(0, $result['failed']);

        Http::assertSentCount(3); // multicast 1回（失敗）+ push 2回
    }

    public function test_push_failure_marks_recipient_failed(): void
    {
        Http::fake([
            'api.line.me/v2/bot/message/multicast' => Http::response('error', 500),
            'api.line.me/v2/bot/message/push' => Http::response('blocked', 400),
        ]);

        $broadcast = $this->makeBroadcast();
        $c1 = $this->makeContact('U0001');

        $result = app(LineBroadcastSender::class)->send($broadcast, collect([$c1]));

        $this->assertSame(0, $result['sent']);
        $this->assertSame(1, $result['failed']);
        $this->assertSame(LineBroadcastRecipient::STATUS_FAILED, LineBroadcastRecipient::first()->status);
    }

    public function test_failed_recipient_can_be_retried_without_duplicate_row(): void
    {
        Http::fake(['api.line.me/*' => Http::response([], 200)]);

        $broadcast = $this->makeBroadcast();
        $c1 = $this->makeContact('U0001');

        // 前回失敗した宛先が残っている状態を再現
        LineBroadcastRecipient::create([
            'line_broadcast_id' => $broadcast->id,
            'customer_line_contact_id' => $c1->id,
            'line_user_id' => 'U0001',
            'status' => LineBroadcastRecipient::STATUS_FAILED,
            'error_message' => 'previous failure',
        ]);

        // 失敗した宛先は再送信の対象になる（送信済みスキップされない・行も増えない）
        $retry = app(LineBroadcastSender::class)->send($broadcast, collect([$c1]));

        $this->assertSame(1, $retry['sent']);
        $this->assertSame(0, $retry['skipped_already_sent']);
        $this->assertSame(LineBroadcastRecipient::STATUS_SENT, LineBroadcastRecipient::first()->status);
        $this->assertNull(LineBroadcastRecipient::first()->error_message);
        $this->assertSame(1, LineBroadcastRecipient::count());
    }
}
