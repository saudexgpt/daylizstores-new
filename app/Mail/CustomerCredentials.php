<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The login details for a customer whose account was created at guest checkout. QUEUED, and ENCRYPTED in the
 * queue table because it carries the customer's temporary password.
 */
class CustomerCredentials extends Mailable implements ShouldQueue, ShouldBeEncrypted
{
    use Queueable, SerializesModels;

    public $tries = 5;
    public $backoff = [30, 120, 600, 1800];   // seconds between attempts
    public $timeout = 60;

    public $user; // public so the view can read them
    public $password;

    public function __construct($user, $password)
    {
        $this->user = $user;
        $this->password = $password;
        $this->afterCommit();   // only queue once the surrounding database transaction has committed
    }

    public function build()
    {
        $user = $this->user;
        $password = $this->password;

        return $this->view('emails.customer_credentials', compact('user', 'password'));
    }
}
