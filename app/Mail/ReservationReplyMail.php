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
        // スレッドの基本件名（[threadNo]とRe:を除いた部分）
        $baseSubject = $this->emailThread->subject;
        
        // [threadNo]を削除（既に含まれている場合）
        $baseSubject = preg_replace('/^\[\d+\]\s*/', '', $baseSubject);
        
        // Re:を削除（既に含まれている場合）
        $baseSubject = preg_replace('/^Re:\s*/i', '', $baseSubject);
        $baseSubject = trim($baseSubject);
        
        // Subject形式：返信の場合は "Re: [threadNo] xxx"、新規の場合は "[threadNo] xxx"
        if ($this->inReplyTo !== null) {
            // 返信の場合：Re: を先頭に配置
            $subject = "Re: [{$this->emailThread->id}] {$baseSubject}";
        } else {
            // 新規メールの場合
            $subject = "[{$this->emailThread->id}] {$baseSubject}";
        }
        
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
