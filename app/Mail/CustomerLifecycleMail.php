<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class CustomerLifecycleMail extends Mailable
{
    public array $content;

    public function __construct(array $content)
    {
        $this->content = $content;
    }

    public function build()
    {
        return $this->subject($this->content['subject'])
            ->view('emails.customer-lifecycle');
    }
}
