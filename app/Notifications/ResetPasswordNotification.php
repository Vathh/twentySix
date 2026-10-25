<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Ustaw nowe hasło w twentySix')
            ->greeting('Witaj!')
            ->line('Dostaliśmy prośbę o ustawienie nowego hasła do Twojego konta.')
            ->action('Ustaw nowe hasło', $url)
            ->line('Link jest ważny przez 60 minut. Jeśli to nie Ty prosiłeś o zmianę, zignoruj tę wiadomość.');
    }
}
