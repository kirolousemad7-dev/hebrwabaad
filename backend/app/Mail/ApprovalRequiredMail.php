<?php

namespace App\Mail;

use App\Models\PrintingCustomerApproval;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ApprovalRequiredMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public PrintingCustomerApproval $approval,
        public string $approvalUrl,
        public string $customerName,
    ) {
        $this->afterCommit();
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'مطلوب موافقتكم',
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
        $title = e((string) $this->approval->title);
        $url = e($this->approvalUrl);

        return <<<HTML
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head><meta charset="utf-8"><title>موافقة مطلوبة</title></head>
<body style="font-family:Tahoma,Arial,sans-serif;line-height:1.6;color:#222">
  <p>مرحبًا {$name}،</p>
  <p>نحتاج موافقتكم على: <strong>{$title}</strong>.</p>
  <p><a href="{$url}">مراجعة والموافقة</a></p>
  <p>مع تحيات {$appName}</p>
</body>
</html>
HTML;
    }
}
