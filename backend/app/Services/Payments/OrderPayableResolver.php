<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\OrderItem;

class OrderPayableResolver
{
    public function resolve(Order $order): ?PayableQuote
    {
        $order->loadMissing(['package', 'packageTier', 'service', 'items', 'addons.addon']);

        if ($order->is_custom_package || $order->items->isNotEmpty()) {
            return $this->resolveCustomPackage($order);
        }

        $tier = $order->packageTier;

        if ($tier !== null) {
            if (! $tier->isPriced()) {
                return null;
            }

            $quote = new PayableQuote(
                number_format((float) $tier->price, 2, '.', ''),
                strtoupper((string) ($tier->currency ?: $order->package?->currency ?: 'SAR')),
            );

            return $quote->isPayable() ? $quote : null;
        }

        if ($order->package !== null) {
            if (! $order->package->isChargeable()) {
                return null;
            }

            $quote = new PayableQuote(
                $order->package->finalPrice(),
                strtoupper((string) ($order->package->currency ?: 'SAR')),
            );

            return $quote->isPayable() ? $quote : null;
        }

        if ($order->service !== null) {
            if (! $order->service->isChargeable()) {
                return null;
            }

            $quote = new PayableQuote(
                number_format((float) $order->service->base_price, 2, '.', ''),
                strtoupper((string) ($order->service->currency ?: 'SAR')),
            );

            return $quote->isPayable() ? $quote : null;
        }

        return null;
    }

    private function resolveCustomPackage(Order $order): ?PayableQuote
    {
        if ($order->requires_quote || $order->items->isEmpty()) {
            return null;
        }

        $total = '0.00';
        $currency = 'SAR';

        foreach ($order->items as $item) {
            /** @var OrderItem $item */
            if (! $item->isChargeable()) {
                return null;
            }

            $line = $item->lineTotal();
            if ($line === null) {
                return null;
            }

            $total = bcadd($total, $line, 2);
            $currency = strtoupper((string) ($item->currency ?: 'SAR'));
        }

        foreach ($order->addons as $orderAddon) {
            $addon = $orderAddon->addon;
            if ($addon === null || ! $addon->isChargeable()) {
                return null;
            }

            $addonTotal = bcmul(
                number_format((float) $addon->price, 2, '.', ''),
                (string) max(1, (int) $orderAddon->quantity),
                2,
            );
            $total = bcadd($total, $addonTotal, 2);
        }

        $quote = new PayableQuote($total, $currency);

        return $quote->isPayable() ? $quote : null;
    }

    /**
     * Machine-readable explanation for a non-payable order, used by the customer payment page.
     */
    public function unavailableReason(Order $order): string
    {
        $order->loadMissing(['package', 'packageTier', 'service', 'items']);

        if ($order->is_custom_package || $order->items->isNotEmpty()) {
            if ($order->requires_quote || $order->items->contains(fn (OrderItem $item) => ! $item->isChargeable())) {
                return 'awaiting_owner_quote';
            }

            return 'order_has_no_catalog_price';
        }

        $tier = $order->packageTier;

        if ($tier !== null && ! $tier->isPriced()) {
            return 'awaiting_owner_quote';
        }

        if ($order->package !== null && ! $order->package->pricingMode()->isChargeable()) {
            return 'awaiting_owner_quote';
        }

        if ($order->service !== null && ! $order->service->pricingMode()->isChargeable()) {
            return 'awaiting_owner_quote';
        }

        return 'order_has_no_catalog_price';
    }
}
