<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Invitation dans l'équipe d'un producteur.
 *
 * Deux cas :
 *  - nouveau compte : le mot de passe généré figure dans l'email (jamais
 *    stocké en clair, comme pour ProducerCredentialsMail) ;
 *  - abonné existant rattaché à l'équipe : $password est NULL, il se connecte
 *    avec son mot de passe habituel (lien « mot de passe oublié » fourni).
 */
class TeamInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    /** @param list<string> $modules libellés des modules délégués */
    public function __construct(
        public string $name,
        public string $producerName,
        public string $memberEmail,
        public ?string $password,
        public array $modules,
        public string $loginUrl,
        public string $forgotUrl,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Vous rejoignez l'équipe de {$this->producerName} — ABBEV",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.team-invitation',
        );
    }
}
