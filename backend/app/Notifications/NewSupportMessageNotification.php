<?php

namespace App\Notifications;

use App\Models\Conversation;
use Illuminate\Notifications\Notification;

class NewSupportMessageNotification extends Notification
{
    public function __construct(
        private readonly Conversation $conversation,
        private readonly string $href,
        private readonly bool $forCustomer,
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
            'type' => 'new_support_message',
            'title' => $this->forCustomer ? 'رسالة جديدة من فريق HEBR' : 'رسالة جديدة من عميل',
            'message' => $this->forCustomer
                ? 'لديك رسالة جديدة بخصوص '.$this->contextLabel().'.'
                : 'رسالة جديدة في المحادثة '.$this->conversation->reference.'.',
            'href' => $this->href,
            'conversation_id' => $this->conversation->id,
            'conversation_reference' => $this->conversation->reference,
        ];
    }

    private function contextLabel(): string
    {
        if ($this->conversation->order?->reference) {
            return 'طلبك '.$this->conversation->order->reference;
        }

        if ($this->conversation->project?->title) {
            return 'مشروع '.$this->conversation->project->title;
        }

        return 'محادثتك مع الدعم';
    }
}
