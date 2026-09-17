<?php

namespace App\Notifications;

use App\Models\Supplier;
use Illuminate\Notifications\Notification;

class SupplierRegistrationPendingNotification extends Notification
{
    public function __construct(private readonly Supplier $supplier) {}

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
            'type' => 'supplier_registration_pending',
            'title' => 'طلب تسجيل مورد بانتظار الموافقة',
            'body' => ($this->supplier->display_name ?: $this->supplier->name).' — '.$this->supplier->email,
            'href' => '/owner/suppliers/'.$this->supplier->id,
            'supplier_id' => $this->supplier->id,
            'supplier_code' => $this->supplier->supplier_code,
        ];
    }
}
