<?php

namespace App\Services\Delivery;

use App\Contracts\Shipping\ShippingProvider;

/**
 * In-house pickup / manual courier — no external carrier API.
 */
class ManualDeliveryProvider implements ShippingProvider
{
    public const KEY = 'manual';

    public function key(): string
    {
        return self::KEY;
    }

    /**
     * @param  array<string, mixed>  $shipment
     * @return array<string, mixed>|null
     */
    public function createShipment(array $shipment): ?array
    {
        return null;
    }
}
