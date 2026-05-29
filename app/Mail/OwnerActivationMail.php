<?php

declare(strict_types=1);

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OwnerActivationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $activationUrl,
    ) {}

    /** Return the message envelope. */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Activa tu cuenta en Kibi',
        );
    }

    /** Return the message content definition. */
    public function content(): Content
    {
        return new Content(
            view: 'emails.owner-activation',
        );
    }
}
