<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Email de réinitialisation de mot de passe pour le dashboard web ABBEV.
 * En français, pointant vers la route admin.password.reset. L'expéditeur (From)
 * et le mailer sont ceux configurés dans l'onglet Email de la Configuration.
 */
class AdminResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(public string $token)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = route('admin.password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        $expire = config('auth.passwords.'.config('auth.defaults.passwords', 'users').'.expire', 60);

        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe — ABBEV')
            ->view('emails.reset-password', [
                'url'    => $url,
                'expire' => $expire,
            ]);
    }
}
