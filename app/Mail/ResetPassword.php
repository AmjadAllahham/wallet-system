<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPassword extends Mailable
{
    public $resetCode;
    public function __construct($resetCode)
    {
        $this->resetCode = $resetCode;
    }
    public function build()
    {
        return $this->subject('Code for Reset Password')
            ->html('<p>Your code is: ' . $this->resetCode . '</p>');
    }
}
