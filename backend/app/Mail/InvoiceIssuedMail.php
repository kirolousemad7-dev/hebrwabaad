<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class InvoiceIssuedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public string $customerName,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'فاتورة '.$this->invoice->number,
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
        $number = e((string) $this->invoice->number);
        $total = e((string) $this->invoice->total);
        $currency = e((string) $this->invoice->currency);
        $frontend = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $link = e($frontend.'/dashboard/invoices/'.$this->invoice->id);

        return <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><title>فاتورة</title></head>
<body style="font-family:Tahoma,Arial,sans-serif;line-height:1.6;color:#222">
  <p>مرحبًا {$name}،</p>
  <p>تم إصدار الفاتورة <strong>{$number}</strong> بمبلغ <strong>{$total} {$currency}</strong>.</p>
  <p><a href="{$link}">عرض الفاتورة</a></p>
  <p>مع تحيات {$appName}</p>
</body>
</html>
HTML;
    }
}
