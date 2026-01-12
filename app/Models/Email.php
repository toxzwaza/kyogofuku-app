<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Email extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'ses_smtp_id',
        'from',
        'to',
        'subject',
        'in_reply_to',
        'references',
        'text_body',
        'html_body',
        'raw_email',
        'event_reservation_id',
        'email_thread_id',
    ];

    /**
     * Get the attachments for the email.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class);
    }

    /**
     * Get the event reservation that owns the email.
     */
    public function eventReservation()
    {
        return $this->belongsTo(EventReservation::class);
    }

    /**
     * Get the email thread that owns the email.
     */
    public function emailThread()
    {
        return $this->belongsTo(EmailThread::class);
    }
}
