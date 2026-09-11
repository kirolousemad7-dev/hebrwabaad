<?php

namespace App\Mail;

use App\Models\PrintingQuotation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PrintingQuotationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public PrintingQuotation $quotation,
        public string $publicUrl,
        public string $customerName,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'عرض السعر الخاص بطلبكم',
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
        $reference = e((string) $this->quotation->reference);
        $validUntil = e((string) ($this->quotation->valid_until?->toDateString() ?? '—'));
        $url = e($this->publicUrl);

        return <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><title>عرض السعر</title></head>
<body style="font-family:Tahoma,Arial,sans-serif;line-height:1.6;color:#222">
  <p>مرحبًا {$name}،</p>
  <p>تجدون عرض السعر رقم <strong>{$reference}</strong> من {$appName}.</p>
  <p>صلاحية العرض حتى: {$validUntil}</p>
  <p><a href="{$url}">عرض السعر الآمن</a></p>
  <p>مع تحيات {$appName}</p>
</body>
</html>
HTML;
    }
}
