<?php

namespace App\Services\Consultant;

use App\Support\PrintingCatalog;

class RecommendationValidator
{
    /**
     * Drop any package/service/price that is not in the live catalog snapshot.
     *
     * @param  array<string, mixed>  $payload
     * @param  array{packages: list<array<string, mixed>>, services: list<array<string, mixed>>, printing_slugs: list<string>}  $catalog
     * @return array<string, mixed>
     */
    public function validate(array $payload, array $catalog): array
    {
        $packages = [];
        foreach ($catalog['packages'] as $package) {
            $packages[$package['slug']] = $package;
        }

        $services = [];
        foreach ($catalog['services'] as $service) {
            $services[$service['slug']] = $service;
        }

        foreach (['best_match', 'alternative'] as $key) {
            $item = $payload[$key] ?? null;
            if (! is_array($item)) {
                $payload[$key] = null;

                continue;
            }

            $live = $packages[$item['slug'] ?? ''] ?? null;
            if (! $live || ! ($live['is_active'] ?? false)) {
                $payload[$key] = null;

                continue;
            }

            $payload[$key] = array_merge($item, [
                'id' => $live['id'],
                'name' => $live['name'],
                'description' => $live['description'],
                'price' => $live['price'],
                'discount_amount' => $live['discount_amount'],
                'final_price' => $live['final_price'],
                'currency' => $live['currency'],
                'duration_days' => $live['duration_days'],
                'items' => $live['items'],
            ]);
        }

        $validServices = [];
        foreach ($payload['services'] ?? [] as $service) {
            if (! is_array($service)) {
                continue;
            }
            $live = $services[$service['slug'] ?? ''] ?? null;
            if (! $live) {
                continue;
            }
            $validServices[] = array_merge($service, [
                'id' => $live['id'],
                'name' => $live['name'],
                'summary' => $live['summary'],
                'base_price' => $live['base_price'],
                'currency' => $live['currency'],
                'duration_days' => $live['duration_days'],
            ]);
        }
        $payload['services'] = $validServices;

        $printing = $payload['printing'] ?? null;
        if (is_array($printing)) {
            $slug = $printing['product_slug'] ?? null;
            if (is_string($slug) && $slug !== '' && ! PrintingCatalog::hasSlug($slug)) {
                $printing['product_slug'] = null;
                $printing['starting_price'] = null;
                $printing['requires_quote'] = true;
                $printing['cta'] = [
                    'type' => 'printing',
                    'label' => 'استكشف الطباعة والتغليف',
                    'path' => '/printing-packaging',
                ];
            }
            $payload['printing'] = $printing;
        }

        $cta = $payload['cta'] ?? null;
        if (! is_array($cta) || ! isset($cta['path'], $cta['type'], $cta['label'])) {
            $payload['cta'] = [
                'type' => 'talk_expert',
                'label' => 'تحدث مع مختص',
                'path' => '/consultant?lead=1',
            ];
        }

        return $payload;
    }
}
