<?php

namespace App\Services\Delivery;

use App\Contracts\Shipping\ShippingProvider;
use InvalidArgumentException;

/**
 * Registry of configured delivery/shipping providers.
 *
 * Only MANUAL is configured — no fake carrier integrations.
 */
class DeliveryProviderManager
{
    public const MANUAL = 'MANUAL';

    /**
     * @var array<string, ShippingProvider>
     */
    private array $providers = [];

    public function __construct()
    {
        $available = config('operations.delivery_providers.available', [ManualDeliveryProvider::KEY]);
        if (! is_array($available) || $available === []) {
            $available = [ManualDeliveryProvider::KEY];
        }

        if (in_array(ManualDeliveryProvider::KEY, $available, true)
            || in_array(strtolower(self::MANUAL), array_map('strtolower', $available), true)
        ) {
            $this->register(new ManualDeliveryProvider);
        }
    }

    public function register(ShippingProvider $provider): void
    {
        $this->providers[strtoupper($provider->key())] = $provider;
    }

    public function has(string $provider): bool
    {
        return isset($this->providers[strtoupper(trim($provider))]);
    }

    public function driver(string $provider): ShippingProvider
    {
        $key = strtoupper(trim($provider));
        if (! isset($this->providers[$key])) {
            throw new InvalidArgumentException('Delivery provider is not configured: '.$provider);
        }

        return $this->providers[$key];
    }

    /**
     * @return list<string>
     */
    public function configured(): array
    {
        return array_keys($this->providers);
    }

    /**
     * Feature-flag style list of available provider keys (lowercase UI values).
     *
     * @return list<string>
     */
    public function available(): array
    {
        return array_values(array_map(
            static fn (ShippingProvider $provider): string => strtolower($provider->key()),
            $this->providers,
        ));
    }

    public function defaultProvider(): string
    {
        return self::MANUAL;
    }

    public function assertConfigured(string $provider): void
    {
        if (! $this->has($provider)) {
            throw new InvalidArgumentException('Delivery provider is not configured: '.$provider);
        }
    }
}
