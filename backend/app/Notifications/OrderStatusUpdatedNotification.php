<?php

namespace App\Notifications;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Notifications\Notification;

class OrderStatusUpdatedNotification extends Notification
{
    public function __construct(
        private readonly Order $order,
        private readonly OrderStatus $status,
    ) {}

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
        return [
            'type' => 'order_status_updated',
            'title' => 'تحديث على طلبك',
            'message' => 'طلب '.$this->order->reference.' أصبح الآن '.$this->status->label().'.',
            'href' => '/dashboard/orders/'.$this->order->id,
            'order_id' => $this->order->id,
            'order_reference' => $this->order->reference,
        ];
    }
}
