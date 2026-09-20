<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The password-reset code. QUEUED (a slow mail server must never hold the request open) and ENCRYPTED in the
 * queue table, because it carries the reset token.
 */
class ResetPassword extends Mailable implements ShouldQueue, ShouldBeEncrypted
{
    use Queueable, SerializesModels;

    public $tries = 5;
    public $backoff = [30, 120, 600, 1800];   // seconds between attempts
    public $timeout = 60;

    public $user; // public so the view can read them
    public $token;

    public function __construct($user, $token)
    {
        $this->user = $user;
        $this->token = $token;
        $this->afterCommit();   // only queue once the surrounding database transaction has committed
    }

    public function build()
    {
        $user = $this->user;
        $token = $this->token;

        return $this->view('emails.reset_password', compact('user', 'token'));
    }
}
