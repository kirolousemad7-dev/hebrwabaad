<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Notifications\Notification;

class OwnerPaymentReceivedNotification extends Notification
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
            'type' => 'owner_payment_received',
            'title' => 'دفعة جديدة',
            'message' => 'تم تأكيد دفعة لطلب '.$this->payment->order?->reference.'.',
            'href' => '/owner/payments/'.$this->payment->id,
            'payment_id' => $this->payment->id,
            'order_id' => $this->payment->order_id,
            'order_reference' => $this->payment->order?->reference,
        ];
    }
}
