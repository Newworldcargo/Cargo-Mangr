<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OTPMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;
    public $customerName;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($otp, $customerName = 'Customer')
    {
        $this->otp = $otp;
        $this->customerName = trim((string) $customerName) ?: 'Customer';
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->subject('Your OTP Code')
            ->view('emails.otp')
            ->with([
                'otp' => $this->otp,
                'customerName' => $this->customerName,
            ]);
    }
}
