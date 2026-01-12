<?php

namespace App\Mail;

use App\Models\EmailThread;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReservationReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public $emailThread;
    public $replyMessage;
    public $toEmail;
    public $inReplyTo;
    public $references;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(EmailThread $emailThread, $toEmail, $message, $inReplyTo = null, $references = null)
    {
        $this->emailThread = $emailThread;
        $this->toEmail = $toEmail;
        $this->replyMessage = $message;
        $this->inReplyTo = $inReplyTo;
        $this->references = $references;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        // スレッド番号を件名に追加
        $baseSubject = $this->emailThread->subject;
        
        // 新規メール（inReplyToがnull）の場合はRe:を付けない
        // 既存スレッドへの返信の場合はRe:を追加
        if ($this->inReplyTo !== null) {
            // 既にRe:が含まれていない場合のみ追加
            if (!preg_match('/^Re:\s*/i', $baseSubject)) {
                $baseSubject = 'Re: ' . $baseSubject;
            }
        }
        
        $subject = "[{$this->emailThread->id}] {$baseSubject}";
        
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
            text: 'mail.reservation_reply_plain',
            with: [
                'emailThread' => $this->emailThread,
                'replyMessage' => $this->replyMessage,
            ],
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
