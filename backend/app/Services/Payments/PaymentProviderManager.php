<?php

namespace App\Services\Payments;

class PaymentProviderManager
{
    public function __construct(
        private readonly CardPaymentGateway $cards,
        private readonly PayTabsConfigurationValidator $paytabsConfig,
    ) {}

    /**
     * Configured payment capabilities for staff/public surfaces.
     *
     * @return array{manual: true, paytabs: bool, paytabs_config: array<string, mixed>}
     */
    public function providers(): array
    {
        $config = $this->paytabsConfig->status();

        return [
            'manual' => true,
            'paytabs' => $this->cards->isConfigured(),
            'paytabs_config' => $config,
        ];
    }

    public function paytabsAvailable(): bool
    {
        return $this->cards->isConfigured();
    }

    /**
     * @return array<string, mixed>
     */
    public function paytabsConfiguration(): array
    {
        return $this->paytabsConfig->status();
    }
}
