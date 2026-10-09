<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetCustomerPassword extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Atur ulang password akun '.config('site.name'))
            ->greeting('Halo, '.$notifiable->name.'.')
            ->line('Kami menerima permintaan mengatur ulang password akun Anda.')
            ->action('Atur ulang password', $this->resetUrl($notifiable))
            ->line('Tautan berlaku '.config('auth.passwords.'.config('auth.defaults.passwords').'.expire').' menit. Jika bukan Anda yang meminta, abaikan email ini; password Anda tidak berubah.')
            ->salutation('Salam, '.config('site.name'));
    }
}
