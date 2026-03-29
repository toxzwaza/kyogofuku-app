<?php

namespace App\Mail;

use App\Models\EventReservation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $reservation;

    public $emailThreadId;

    public ?string $lineLiffUrl = null;

    public string $lineAddFriendUrl = '';

    public bool $reservationEmailIncludeAddFriendUrl = true;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(EventReservation $reservation, $emailThreadId = null, ?string $lineLiffUrl = null)
    {
        $this->reservation = $reservation;
        $this->emailThreadId = $emailThreadId;
        $this->lineLiffUrl = $lineLiffUrl;
        $this->lineAddFriendUrl = (string) config('line.line_official_add_friend_url', '');
        $this->reservationEmailIncludeAddFriendUrl = (bool) config('line.reservation_email_include_add_friend_url', true);
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        $eventTitle = $this->reservation->event->title ?? 'イベント';
        $baseSubject = "【{$eventTitle}】ご予約ありがとうございます";

        // スレッド番号を件名に追加
        $subject = $this->emailThreadId
            ? "[{$this->emailThreadId}] {$baseSubject}"
            : $baseSubject;

        return new Envelope(
            subject: $subject,
            replyTo: 'reply@reply.kyogofuku-hirata.jp',
        );
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    public function content()
    {
        return new Content(
            html: 'mail.reservation_confirmation',
            text: 'mail.reservation_confirmation_plain',
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array
     */
    public function attachments()
    {
        return [];
    }
}
