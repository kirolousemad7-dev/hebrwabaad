<?php

namespace App\Services\Payments;

use App\Models\Order;

class OrderPayableResolver
{
    public function resolve(Order $order): ?PayableQuote
    {
        $order->loadMissing(['package', 'packageTier', 'service']);

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

    /**
     * Machine-readable explanation for a non-payable order, used by the customer payment page.
     */
    public function unavailableReason(Order $order): string
    {
        $order->loadMissing(['package', 'packageTier', 'service']);

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
