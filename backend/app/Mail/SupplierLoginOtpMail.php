<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupplierLoginOtpMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $code,
        public readonly string $supplierName,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'رمز دخول بوابة الموردين — هبر وأبعاد',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>مرحباً '.e($this->supplierName).'،</p>'
                .'<p>رمز الدخول لمرة واحدة هو: <strong>'.e($this->code).'</strong></p>'
                .'<p>صالح لمدة 10 دقائق. إذا لم تطلب هذا الرمز فتجاهل الرسالة.</p>',
        );
    }
}
