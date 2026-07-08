<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    public function __construct(private string $token) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $url = config('app.frontend_url', 'http://localhost:5173')
             . '/reset-password?token=' . $this->token
             . '&email=' . urlencode($notifiable->email);

        return (new MailMessage)
            ->subject('Réinitialisation de votre mot de passe DOKITA')
            ->greeting('Bonjour ' . $notifiable->name . ' !')
            ->line('Vous avez demandé une réinitialisation de mot de passe.')
            ->action('Réinitialiser mon mot de passe', $url)
            ->line('Ce lien expire dans 60 minutes.')
            ->line('Si vous n\'avez pas fait cette demande, ignorez cet email.')
            ->salutation('L\'équipe DOKITA');
    }
}