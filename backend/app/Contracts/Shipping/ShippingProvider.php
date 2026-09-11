<?php

namespace App\Contracts\Shipping;

/**
 * Carrier/shipping provider contract.
 *
 * Only ManualDeliveryProvider is registered today — no DHL/Aramex.
 */
interface ShippingProvider
{
    public function key(): string;

    /**
     * Create an external shipment when the provider supports it.
     * Manual providers return null (no-op).
     *
     * @param  array<string, mixed>  $shipment
     * @return array<string, mixed>|null
     */
    public function createShipment(array $shipment): ?array;
}
