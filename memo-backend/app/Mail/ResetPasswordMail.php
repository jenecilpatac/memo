<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public $token;
    public $firstname;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($token,$firstname)
    {
        $this->token = $token;
        $this->firstname = $firstname;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.reset_password')
                    ->subject('Reset Password  Notification')
                    ->with([
                        'token' => $this->token,
                        'firstname' => $this->firstname,
                    ]);

                    
    }
}