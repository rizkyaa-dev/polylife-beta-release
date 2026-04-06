<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail as BaseVerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

class VerifyEmailNotification extends BaseVerifyEmail
{
    protected function buildMailMessage($url): MailMessage
    {
        $appName = (string) config('app.name', 'PolyLife');
        if (trim($appName) === '' || strtolower(trim($appName)) === 'laravel') {
            $appName = 'PolyLife';
        }

        $supportEmail = (string) config('mail.from.address', 'support@polylife.app');
        if (trim($supportEmail) === '') {
            $supportEmail = 'support@polylife.app';
        }

        return (new MailMessage)
            ->subject('Verifikasi Email '.$appName)
            ->view(
                [
                    'html' => 'emails.verify-email',
                    'text' => 'emails.verify-email-text',
                ],
                [
                    'appName' => $appName,
                    'supportEmail' => $supportEmail,
                    'verificationUrl' => $url,
                    'expiresInMinutes' => (int) config('auth.verification.expire', 60),
                ]
            );
    }
}
