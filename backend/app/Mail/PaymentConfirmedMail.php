<?php

namespace App\Mail;

use App\Models\Payment;
use App\Models\PrintingQuotation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentConfirmedMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public PrintingQuotation $quotation,
        public Payment $payment,
        public string $customerName,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'تأكيد استلام الدفعة',
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
        $amount = e((string) $this->payment->amount);
        $currency = e((string) $this->payment->currency);

        return <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><title>تأكيد الدفع</title></head>
<body style="font-family:Tahoma,Arial,sans-serif;line-height:1.6;color:#222">
  <p>مرحبًا {$name}،</p>
  <p>تم تأكيد دفعتكم بمبلغ <strong>{$amount} {$currency}</strong> لعرض السعر {$reference}.</p>
  <p>مع تحيات {$appName}</p>
</body>
</html>
HTML;
    }
}
