<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CustomerLoginOtpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly string $customerName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'رمز الدخول — هبر وأبعاد',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>مرحباً '.e($this->customerName).'،</p>'
                .'<p>رمز الدخول لمرة واحدة هو: <strong>'.e($this->code).'</strong></p>'
                .'<p>صالح لمدة 10 دقائق. إذا لم تطلب هذا الرمز فتجاهل الرسالة.</p>',
        );
    }
}
