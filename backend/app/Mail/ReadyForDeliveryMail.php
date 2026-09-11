<?php

namespace App\Mail;

use App\Models\PrintingRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ReadyForDeliveryMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public PrintingRequest $printingRequest,
        public string $customerName,
        public ?string $portalUrl = null,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'طلبكم جاهز للتسليم',
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
        $product = e((string) ($this->printingRequest->product_name ?? 'طلبكم'));
        $portal = $this->portalUrl !== null && $this->portalUrl !== ''
            ? '<p><a href="'.e($this->portalUrl).'">متابعة الطلب من البوابة</a></p>'
            : '';

        return <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><title>جاهز للتسليم</title></head>
<body style="font-family:Tahoma,Arial,sans-serif;line-height:1.6;color:#222">
  <p>مرحبًا {$name}،</p>
  <p>طلبكم <strong>{$product}</strong> أصبح جاهزًا للتسليم أو الاستلام.</p>
  {$portal}
  <p>مع تحيات {$appName}</p>
</body>
</html>
HTML;
    }
}
