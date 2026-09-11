<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PortalMagicLinkMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $customerName,
        public string $portalUrl,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'رابط بوابة العميل',
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->renderHtml(),
        );
    }

    private function renderHtml(): string
    {
        $appName = e((string) config('app.name', 'حبر وأبعاد'));
        $name = e($this->customerName !== '' ? $this->customerName : 'عميلنا الكريم');
        $url = e($this->portalUrl);

        return <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><title>بوابة العميل</title></head>
<body style="font-family:Tahoma,Arial,sans-serif;line-height:1.6;color:#222">
  <p>مرحبًا {$name}،</p>
  <p>يمكنكم الدخول إلى بوابة الطلبات عبر الرابط الآمن التالي:</p>
  <p><a href="{$url}">فتح بوابة العميل</a></p>
  <p>مع تحيات {$appName}</p>
</body>
</html>
HTML;
    }
}
