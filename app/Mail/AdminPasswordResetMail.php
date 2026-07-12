<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email envoyé à un utilisateur quand un admin réinitialise son mot de passe
 * depuis le dashboard. Contient le nouveau mot de passe (jamais stocké en clair).
 */
class AdminPasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $accountEmail,
        public string $password,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Votre mot de passe a été réinitialisé — ABBEV',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.admin-password-reset',
        );
    }
}
