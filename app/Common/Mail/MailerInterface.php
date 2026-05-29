<?php

namespace App\Common\Mail;

interface MailerInterface
{
    /**
     * Send an account activation email to the given address.
     *
     * @param  string  $to  Recipient email address.
     * @param  string  $activationUrl  The signed URL the recipient must visit to activate their account.
     */
    public function sendActivation(string $to, string $activationUrl): void;
}
