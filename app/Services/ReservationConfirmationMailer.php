<?php

namespace App\Services;

use App\Mail\ReservationConfirmationMail;
use App\Models\CustomerLineLinkToken;
use App\Models\Email;
use App\Models\EmailThread;
use App\Models\EventReservation;
use App\Services\Line\EventLineShopResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * 予約者向け「受付完了メール」の送信ロジックを集約するサービス。
 *
 * Vue 版予約フォーム（EventReservationController）と Blade LP 版予約フォーム
 * （BladeReservationController）の両方から共通利用される。
 *
 * - Mail 送信 + Email/EmailThread DB 記録 + LINE LIFF URL 発行を1メソッドにまとめる
 * - 送信失敗時は呼び出し元で try/catch する設計（Service 内では例外を握りつぶさない）
 */
class ReservationConfirmationMailer
{
    public function __construct(
        private EventLineShopResolver $eventLineShopResolver,
    ) {}

    public function send(EventReservation $reservation): void
    {
        $reservation->load('event', 'document');

        $lineLiffUrl = $this->issueLineLiffUrl($reservation);

        $event = $reservation->event;
        $eventTitle = $event?->title ?? 'イベント';

        $mailViewData = [
            'reservation' => $reservation,
            'lineLiffUrl' => $lineLiffUrl,
            'lineAddFriendUrl' => (string) config('line.line_official_add_friend_url', ''),
            'reservationEmailIncludeAddFriendUrl' => (bool) config('line.reservation_email_include_add_friend_url', true),
        ];

        $emailThread = EmailThread::firstOrCreate(
            ['event_reservation_id' => $reservation->id],
            ['subject' => "【{$eventTitle}】ご予約ありがとうございます"]
        );

        $mailable = new ReservationConfirmationMail($reservation, $emailThread->id, $lineLiffUrl);
        Mail::to($reservation->email)->send($mailable);

        $rawEmail = $this->buildRawEmail($mailable, $reservation->email, $mailViewData);
        $messageId = $this->buildMessageId($reservation->id);

        Email::create([
            'message_id' => $messageId,
            'from' => config('mail.from.address'),
            'to' => $reservation->email,
            'subject' => $mailable->envelope()->subject,
            'text_body' => view('mail.reservation_confirmation_plain', $mailViewData)->render(),
            'html_body' => view('mail.reservation_confirmation', $mailViewData)->render(),
            'raw_email' => $rawEmail,
            'event_reservation_id' => $reservation->id,
            'email_thread_id' => $emailThread->id,
        ]);

        Log::info('受付完了メールを送信しました', [
            'reservation_id' => $reservation->id,
            'email' => $reservation->email,
            'email_thread_id' => $emailThread->id,
        ]);
    }

    /**
     * イベントに店舗が紐付いていれば、初回友だち追加用の LIFF URL を発行する。
     * shop が解決できない場合は null を返す（メールから LIFF ボタンが消える）。
     */
    private function issueLineLiffUrl(EventReservation $reservation): ?string
    {
        $event = $reservation->event;
        if (! $event) {
            return null;
        }

        $shopId = $this->eventLineShopResolver->resolveShopIdForEvent($event);
        if ($shopId === null) {
            return null;
        }

        $token = CustomerLineLinkToken::generateToken();
        CustomerLineLinkToken::query()->create([
            'token' => $token,
            'customer_id' => null,
            'event_reservation_id' => $reservation->id,
            'shop_id' => $shopId,
            'suggested_label' => '本人',
            'expires_at' => now()->addDays(30),
            'created_by_user_id' => null,
        ]);

        return rtrim((string) config('app.url'), '/').'/line/liff/link/'.rawurlencode($token);
    }

    private function buildRawEmail(ReservationConfirmationMail $mailable, string $to, array $mailViewData): string
    {
        $envelope = $mailable->envelope();
        $subject = $envelope->subject;
        $from = config('mail.from.address');
        $fromName = config('mail.from.name');

        $textBody = view('mail.reservation_confirmation_plain', $mailViewData)->render();

        $rawEmail  = "From: {$fromName} <{$from}>\r\n";
        $rawEmail .= "To: {$to}\r\n";
        $rawEmail .= "Subject: {$subject}\r\n";
        $rawEmail .= "Reply-To: reply@reply.kyogofuku-hirata.jp\r\n";
        $rawEmail .= 'Date: '.now()->format('r')."\r\n";
        $rawEmail .= "MIME-Version: 1.0\r\n";
        $rawEmail .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $rawEmail .= "\r\n";
        $rawEmail .= $textBody;

        return $rawEmail;
    }

    private function buildMessageId(int $reservationId): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';

        return '<reservation-confirmation-'.$reservationId.'-'.now()->timestamp.'@'.$host.'>';
    }
}
