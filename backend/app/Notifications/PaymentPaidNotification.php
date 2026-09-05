<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Notifications\Notification;

class PaymentPaidNotification extends Notification
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
            'type' => 'payment_paid',
            'title' => 'تم تأكيد الدفع',
            'message' => 'تم تأكيد دفع طلب '.$this->payment->order?->reference.'.',
            'href' => '/dashboard/orders/'.$this->payment->order_id.'/pay',
            'payment_id' => $this->payment->id,
            'order_id' => $this->payment->order_id,
            'order_reference' => $this->payment->order?->reference,
        ];
    }
}
