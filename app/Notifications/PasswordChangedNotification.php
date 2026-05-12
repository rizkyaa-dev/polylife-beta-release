<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordChangedNotification extends Notification
{
    public function __construct(
        private readonly ?string $ipAddress = null,
        private readonly ?string $userAgent = null
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $appName = (string) config('app.name', 'PolyLife');
        if (trim($appName) === '' || strtolower(trim($appName)) === 'laravel') {
            $appName = 'PolyLife';
        }

        $supportEmail = (string) config('mail.from.address', 'no-reply@polylife.site');
        if (trim($supportEmail) === '') {
            $supportEmail = 'no-reply@polylife.site';
        }

        return (new MailMessage)
            ->subject('Password '.$appName.' Berhasil Diubah')
            ->view(
                [
                    'html' => 'emails.password-changed',
                    'text' => 'emails.password-changed-text',
                ],
                [
                    'appName' => $appName,
                    'supportEmail' => $supportEmail,
                    'changedAt' => now(),
                    'ipAddress' => $this->ipAddress,
                    'userAgent' => $this->userAgent,
                ]
            );
    }
}
