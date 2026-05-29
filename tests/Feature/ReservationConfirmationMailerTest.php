<?php

namespace Tests\Feature;

use App\Mail\ReservationConfirmationMail;
use App\Models\CustomerLineLinkToken;
use App\Models\Email;
use App\Models\EmailThread;
use App\Models\Event;
use App\Models\EventReservation;
use App\Models\Shop;
use App\Services\ReservationConfirmationMailer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * ReservationConfirmationMailer サービスの単体テスト。
 * Mail::fake() で Mailable 送信を捕捉する。
 */
class ReservationConfirmationMailerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        config(['app.url' => 'https://example.com']);
    }

    private function makeReservation(?Shop $shop = null): EventReservation
    {
        $event = Event::create([
            'slug' => 'evt-' . uniqid(),
            'title' => 'テストイベント',
            'description' => '',
            'form_type' => 'reservation',
            'is_public' => true,
        ]);
        if ($shop) {
            $event->shops()->attach($shop->id);
        }

        return EventReservation::create([
            'event_id' => $event->id,
            'name' => '山田 花子',
            'email' => 'hanako@example.com',
            'phone' => '090-1234-5678',
            'status' => 'pending',
            'privacy_agreed' => true,
            'cancel_flg' => false,
            'visitor_count' => 1,
        ]);
    }

    public function test_sends_mail_and_creates_line_link_token_when_shop_resolves(): void
    {
        $shop = Shop::create([
            'name' => '岡山店',
            'is_active' => true,
            'line_group_id' => 'Cgroup-okayama',
        ]);
        $reservation = $this->makeReservation($shop);

        app(ReservationConfirmationMailer::class)->send($reservation);

        Mail::assertSent(ReservationConfirmationMail::class, function ($mail) use ($reservation) {
            return $mail->hasTo($reservation->email);
        });

        // CustomerLineLinkToken が1件発行されている
        $this->assertEquals(1, CustomerLineLinkToken::where('event_reservation_id', $reservation->id)->count());
        $token = CustomerLineLinkToken::where('event_reservation_id', $reservation->id)->first();
        $this->assertEquals($shop->id, $token->shop_id);
        $this->assertEquals('本人', $token->suggested_label);

        // Email レコードが1件作成されている
        $this->assertEquals(1, Email::where('event_reservation_id', $reservation->id)->count());
        $email = Email::where('event_reservation_id', $reservation->id)->first();
        $this->assertEquals($reservation->email, $email->to);
        $this->assertStringStartsWith('<reservation-confirmation-', $email->message_id);

        // EmailThread が1件作成されている
        $this->assertEquals(1, EmailThread::where('event_reservation_id', $reservation->id)->count());
    }

    public function test_sends_mail_without_line_token_when_shop_not_resolvable(): void
    {
        // shop を attach しない（event に紐づく shop なし）
        $reservation = $this->makeReservation(null);

        app(ReservationConfirmationMailer::class)->send($reservation);

        Mail::assertSent(ReservationConfirmationMail::class, function ($mail) use ($reservation) {
            return $mail->hasTo($reservation->email);
        });

        // LINE トークンは発行されない
        $this->assertEquals(0, CustomerLineLinkToken::where('event_reservation_id', $reservation->id)->count());

        // メール本体・Email レコードは作成される
        $this->assertEquals(1, Email::where('event_reservation_id', $reservation->id)->count());
    }

    public function test_email_record_has_expected_fields(): void
    {
        $shop = Shop::create([
            'name' => '岡山店',
            'is_active' => true,
            'line_group_id' => 'Cgroup-okayama',
        ]);
        $reservation = $this->makeReservation($shop);

        app(ReservationConfirmationMailer::class)->send($reservation);

        $email = Email::where('event_reservation_id', $reservation->id)->firstOrFail();
        $this->assertNotEmpty($email->message_id);
        $this->assertNotEmpty($email->subject);
        $this->assertNotEmpty($email->text_body);
        $this->assertNotEmpty($email->html_body);
        $this->assertNotEmpty($email->raw_email);
        $this->assertNotNull($email->email_thread_id);
    }

    public function test_email_thread_is_reused_on_second_send(): void
    {
        $shop = Shop::create([
            'name' => '岡山店',
            'is_active' => true,
            'line_group_id' => 'Cgroup-okayama',
        ]);
        $reservation = $this->makeReservation($shop);

        $mailer = app(ReservationConfirmationMailer::class);

        $mailer->send($reservation);
        $mailer->send($reservation);

        // EmailThread は 1件のまま
        $this->assertEquals(1, EmailThread::where('event_reservation_id', $reservation->id)->count());
        // Email レコードは 2件
        $this->assertEquals(2, Email::where('event_reservation_id', $reservation->id)->count());
        // LINE トークンは2回発行される（毎回新規発行する仕様）
        $this->assertEquals(2, CustomerLineLinkToken::where('event_reservation_id', $reservation->id)->count());
    }

    public function test_mail_exception_propagates_to_caller(): void
    {
        $reservation = $this->makeReservation(null);

        // Mail::fake() を上書きし、 Mailable 送信時に例外を投げる
        Mail::shouldReceive('to')
            ->andThrow(new \RuntimeException('SMTP down'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('SMTP down');

        app(ReservationConfirmationMailer::class)->send($reservation);
    }
}
