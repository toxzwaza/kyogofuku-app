<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class SesTestMail extends Mailable
{
    public $textMessage;

    public function __construct($textMessage)
    {
        $this->textMessage = $textMessage;
    }

    public function build()
    {
        return $this->subject('Amazon SES テストメール')
            ->text('mail.ses_test_plain')
            ->with(['messageContent' => $this->textMessage]);
    }
}



