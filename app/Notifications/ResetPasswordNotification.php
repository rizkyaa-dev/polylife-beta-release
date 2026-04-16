<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword as BaseResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends BaseResetPassword
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
            ->subject('Reset Password '.$appName)
            ->view(
                [
                    'html' => 'emails.reset-password',
                    'text' => 'emails.reset-password-text',
                ],
                [
                    'appName' => $appName,
                    'supportEmail' => $supportEmail,
                    'resetUrl' => $url,
                    'expiresInMinutes' => (int) config('auth.passwords.'.config('auth.defaults.passwords').'.expire', 60),
                ]
            );
    }
}
