<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Same signed link as Laravel's notification, written in Indonesian for guests.
 */
class VerifyCustomerEmail extends VerifyEmail
{
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Verifikasi email akun '.config('site.name'))
            ->greeting('Halo, '.$notifiable->name.'.')
            ->line('Klik tombol di bawah untuk memverifikasi email Anda. Setelah itu booking lama dengan email ini muncul di halaman akun.')
            ->action('Verifikasi email', $this->verificationUrl($notifiable))
            ->line('Tautan berlaku '.config('auth.verification.expire', 60).' menit. Jika Anda tidak membuat akun, abaikan email ini.')
            ->salutation('Salam, '.config('site.name'));
    }
}
