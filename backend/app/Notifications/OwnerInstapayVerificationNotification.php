<?php

namespace App\Notifications;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use Illuminate\Notifications\Notification;

class OwnerInstapayVerificationNotification extends Notification
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
            'type' => 'owner_instapay_verification',
            'title' => 'تحقق تحويل مطلوب',
            'message' => 'طلب '.$this->payment->order?->reference.' بانتظار مراجعة تحويل '.$this->methodLabel().'.',
            'href' => '/owner/payments/'.$this->payment->id,
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
