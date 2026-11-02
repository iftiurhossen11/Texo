<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;

class TexoResetPassword extends ResetPassword
{
    protected function resetUrl($notifiable): string
    {
        return rtrim((string) config('app.frontend_url'), '/').'/?password-reset=1'
            .'&token='.urlencode($this->token)
            .'&email='.urlencode($notifiable->getEmailForPasswordReset());
    }
}
