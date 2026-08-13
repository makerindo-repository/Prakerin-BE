<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendOtpNotification extends Notification
{
    use Queueable;

    public string $otp;

    /**
     * Create a new notification instance.
     */
    public function __construct(string $otp)
    {
        $this->otp = $otp;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $name = $notifiable->username ?? 'Pengguna';

        return (new MailMessage)
            ->subject('Kode OTP Lupa Password - PRAKERIN.ID')
            ->greeting('Halo, ' . $name)
            ->line('Kami menerima permintaan untuk mengatur ulang password akun Anda.')
            ->line('Berikut adalah kode OTP verifikasi Anda:')
            ->line('# ' . $this->otp)
            ->line('Kode OTP ini berlaku selama 15 menit. Jangan berikan kode ini kepada siapa pun.')
            ->line('Jika Anda tidak merasa meminta reset password, silakan abaikan email ini.');
    }
}
