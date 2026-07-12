<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email envoyé au producteur à la création de son compte (ou lors d'un renvoi
 * des identifiants). Contient l'email de connexion, le mot de passe généré et
 * le lien vers l'espace d'administration.
 *
 * Le mot de passe circule en clair UNIQUEMENT dans cet email : il n'est jamais
 * stocké en clair côté serveur (seul le hash l'est). C'est pourquoi un renvoi
 * régénère toujours un nouveau mot de passe.
 */
class ProducerCredentialsMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $name,
        public string $producerEmail,
        public string $password,
        public string $loginUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Vos identifiants producteur — ABBEV',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.producer-credentials',
        );
    }
}
