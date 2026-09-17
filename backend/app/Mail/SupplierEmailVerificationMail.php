<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SupplierEmailVerificationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly string $supplierName,
        public readonly string $verificationUrl,
        public readonly int $expiresMinutes,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'تأكيد البريد الإلكتروني — هبر وأبعاد',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: '<p>مرحباً '.e($this->supplierName).'،</p>'
                .'<p>يرجى تأكيد بريدك عبر الرابط التالي (ينتهي خلال '.$this->expiresMinutes.' دقيقة):</p>'
                .'<p><a href="'.e($this->verificationUrl).'">'.e($this->verificationUrl).'</a></p>'
                .'<p>إذا لم تطلب هذا فتجاهل الرسالة.</p>',
        );
    }
}
