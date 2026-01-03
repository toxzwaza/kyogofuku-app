<?php

namespace App\Mail;

use App\Models\EventReservation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationConfirmationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $reservation;
    public $emailThreadId;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(EventReservation $reservation, $emailThreadId = null)
    {
        $this->reservation = $reservation;
        $this->emailThreadId = $emailThreadId;
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
