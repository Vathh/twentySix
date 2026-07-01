<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends VerifyEmail
{
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject('Potwierdź rejestrację w twentySix')
            ->greeting('Witaj!')
            ->line('Kliknij przycisk poniżej, aby potwierdzić adres email i aktywować konto.')
            ->action('Potwierdź email', $url)
            ->line('Jeśli to nie Ty zakładałeś konto, zignoruj tę wiadomość.');
    }
}
