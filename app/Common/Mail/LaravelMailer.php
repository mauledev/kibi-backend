<?php

declare(strict_types=1);

namespace App\Common\Mail;

use App\Mail\OwnerActivationMail;
use Illuminate\Support\Facades\Mail;

class LaravelMailer implements MailerInterface
{
    /** {@inheritDoc} */
    public function sendActivation(string $to, string $activationUrl): void
    {
        Mail::to($to)->send(new OwnerActivationMail($activationUrl));
    }
}
