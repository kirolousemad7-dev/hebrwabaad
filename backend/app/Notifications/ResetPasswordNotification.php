<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    public function __construct(public readonly string $token) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = (int) config('auth.passwords.users.expire', 60);

        return (new MailMessage)
            ->subject('إعادة تعيين كلمة المرور — حبر وأبعاد')
            ->line('وصلك هذا البريد لأننا استلمنا طلب إعادة تعيين كلمة المرور لحسابك.')
            ->action('تعيين كلمة مرور جديدة', $this->resetUrl($notifiable))
            ->line('ينتهي هذا الرابط خلال '.$minutes.' دقيقة.')
            ->line('إذا لم تطلب إعادة التعيين فيمكنك تجاهل هذه الرسالة.');
    }

    public function resetUrl(object $notifiable): string
    {
        $email = is_object($notifiable) && method_exists($notifiable, 'getEmailForPasswordReset')
            ? $notifiable->getEmailForPasswordReset()
            : '';

        return rtrim((string) config('app.frontend_url'), '/').'/reset-password?'.http_build_query([
            'token' => $this->token,
            'email' => $email,
        ]);
    }
}
