<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OtoShipment extends Model
{
    protected $fillable = [
        'order_id',
        'oto_order_id',
        'oto_internal_id',
        'shipment_id',
        'delivery_option_id',
        'carrier',
        'tracking_number',
        'tracking_url',
        'awb_url',
        'shipping_cost',
        'currency',
        'weight_kg',
        'status',
        'remote_order_created_at',
        'last_synced_at',
        'last_event_at',
        'delivered_at',
        'error_code',
        'error_message',
        'recipient_name',
        'recipient_phone',
        'address',
        'city',
        'country',
        'quote_options',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'shipping_cost' => 'decimal:2',
            'weight_kg' => 'decimal:2',
            'quote_options' => 'array',
            'remote_order_created_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'last_event_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public static function label(?string $status): string
    {
        return match ($status) {
            'quoted' => 'تم حساب التكلفة',
            'order_created' => 'تم إنشاء طلب الشحن',
            'booked', 'shipmentCreated' => 'تم إنشاء الشحنة',
            'assignedToWarehouse' => 'في المستودع',
            'shipmentProcessing' => 'قيد التجهيز',
            'pickedUp' => 'تم الاستلام',
            'inTransit' => 'في الطريق',
            'outForDelivery' => 'خرج للتسليم',
            'delivered' => 'تم التسليم',
            'returned' => 'مرتجع',
            'canceled', 'cancelled' => 'ملغى',
            'error' => 'خطأ في المزامنة',
            null, '' => 'بانتظار الشحن',
            default => (string) $status,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function customerPayload(): array
    {
        return [
            'status' => $this->status,
            'status_label' => self::label($this->status),
            'carrier' => $this->carrier,
            'tracking_number' => $this->tracking_number,
            'tracking_url' => $this->tracking_url,
            'delivered_at' => $this->delivered_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function ownerPayload(): array
    {
        return [
            ...$this->customerPayload(),
            'oto_order_id' => $this->oto_order_id,
            'shipment_id' => $this->shipment_id,
            'delivery_option_id' => $this->delivery_option_id,
            'shipping_cost' => $this->shipping_cost,
            'currency' => $this->currency,
            'awb_url' => $this->awb_url,
            'error_code' => $this->error_code,
            'error_message' => $this->error_message,
            'last_synced_at' => $this->last_synced_at?->toIso8601String(),
            'recipient_name' => $this->recipient_name,
            'recipient_phone' => $this->recipient_phone,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'quote_options' => $this->quote_options ?? [],
            'remote_order_created' => $this->remote_order_created_at !== null,
        ];
    }
}
