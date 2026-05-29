<?php

namespace Tests\Feature\BladeLp;

use App\Mail\ReservationConfirmationMail;
use App\Models\Event;
use App\Models\EventReservation;
use App\Models\Shop;
use App\Services\BladeLp\LineNotifier;
use App\Services\ReservationConfirmationMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Blade LP 経由の予約完了時に受付完了メールが送られるかの統合テスト。
 */
class BladeReservationMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config([
            'app.url' => 'https://example.com',
            // テスト用の lp_designs テンプレ
            'lp_designs.templates.test-blade.render_type' => 'blade',
        ]);

        // LINE 通知は実行しない（モック化）
        $this->app->bind(LineNotifier::class, function () {
            return new class {
                public function notify($event, $reservation): void {}
            };
        });
    }

    private function makeBladeEvent(?Shop $shop = null): Event
    {
        $event = Event::create([
            'slug' => 'blade-event-' . uniqid(),
            'title' => 'Blade LP テストイベント',
            'description' => '',
            'form_type' => 'reservation',
            'is_public' => true,
            'lp_design_slug' => 'test-blade',
            'form_schema' => [
                ['key' => 'name', 'type' => 'text', 'required' => true],
                ['key' => 'email', 'type' => 'email', 'required' => false],
                ['key' => 'phone', 'type' => 'tel', 'required' => true],
            ],
        ]);
        if ($shop) {
            $event->shops()->attach($shop->id);
        }

        return $event;
    }

    public function test_blade_lp_reservation_sends_confirmation_mail(): void
    {
        $shop = Shop::create([
            'name' => '岡山店',
            'is_active' => true,
            'line_group_id' => 'Cgroup-okayama',
        ]);
        $event = $this->makeBladeEvent($shop);

        $this->post(route('blade-lp.reserve', $event->id), [
            'name' => '山田 花子',
            'email' => 'hanako@example.com',
            'phone' => '090-1234-5678',
        ])->assertRedirect();

        $reservation = EventReservation::where('event_id', $event->id)->firstOrFail();
        $this->assertEquals('hanako@example.com', $reservation->email);

        Mail::assertSent(ReservationConfirmationMail::class, function ($mail) {
            return $mail->hasTo('hanako@example.com');
        });
    }

    public function test_blade_lp_reservation_without_email_does_not_send_mail(): void
    {
        $shop = Shop::create([
            'name' => '岡山店',
            'is_active' => true,
            'line_group_id' => 'Cgroup-okayama',
        ]);
        $event = $this->makeBladeEvent($shop);

        // メール無しで予約
        $this->post(route('blade-lp.reserve', $event->id), [
            'name' => '山田 花子',
            'phone' => '090-1234-5678',
        ])->assertRedirect();

        $this->assertTrue(EventReservation::where('event_id', $event->id)->exists());
        Mail::assertNothingSent();
    }

    public function test_mailer_exception_does_not_break_reservation(): void
    {
        $shop = Shop::create([
            'name' => '岡山店',
            'is_active' => true,
            'line_group_id' => 'Cgroup-okayama',
        ]);
        $event = $this->makeBladeEvent($shop);

        // Mailer に例外を投げさせる
        $mock = $this->mock(ReservationConfirmationMailer::class, function ($mock) {
            $mock->shouldReceive('send')
                ->once()
                ->andThrow(new \RuntimeException('SMTP down'));
        });

        // Log::error が出ることを確認
        Log::shouldReceive('error')
            ->withArgs(function ($message, $context) {
                return str_contains($message, '受付完了メール送信失敗')
                    && ($context['error'] ?? null) === 'SMTP down';
            });
        // 他のログ呼び出しも素通り
        Log::shouldReceive('error');
        Log::shouldReceive('info');
        Log::shouldReceive('warning');

        // 予約処理は成功（リダイレクト）
        $this->post(route('blade-lp.reserve', $event->id), [
            'name' => '山田 花子',
            'email' => 'hanako@example.com',
            'phone' => '090-1234-5678',
        ])->assertRedirect();

        // 予約自体は保存されている
        $this->assertTrue(EventReservation::where('event_id', $event->id)->exists());
    }
}
