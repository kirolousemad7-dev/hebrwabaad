<?php

namespace App\Notifications;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use Illuminate\Notifications\Notification;

class InstapaySubmittedNotification extends Notification
{
    public function __construct(private readonly Payment $payment) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $this->payment->loadMissing('order');

        return [
            'type' => 'payment_instapay_submitted',
            'title' => 'تم استلام بيانات التحويل',
            'message' => 'استلمنا رقم تحويل '.$this->methodLabel().' لطلب '.$this->payment->order?->reference.' وهو قيد التحقق.',
            'href' => '/dashboard/orders/'.$this->payment->order_id.'/pay',
            'payment_id' => $this->payment->id,
            'order_id' => $this->payment->order_id,
            'order_reference' => $this->payment->order?->reference,
        ];
    }

    private function methodLabel(): string
    {
        $method = $this->payment->payment_method;

        return $method instanceof PaymentMethod
            ? $method->label()
            : PaymentMethod::from((string) $method)->label();
    }
}
