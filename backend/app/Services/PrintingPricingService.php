<?php

namespace App\Services;

use App\Enums\PrintingFinishing;
use App\Enums\PrintingMethod;
use App\Enums\PrintingPricingType;
use App\Enums\PrintingShape;
use App\Models\PrintingRequest;
use App\Support\PrintingCatalog;

class PrintingPricingService
{
    /**
     * @return array{pricing_type: PrintingPricingType, estimated_price: string|null, pricing_notes: string}
     */
    public function suggest(PrintingRequest $request): array
    {
        if ($this->requiresManualQuote($request)) {
            return [
                'pricing_type' => PrintingPricingType::QuoteRequired,
                'estimated_price' => null,
                'pricing_notes' => 'يحتاج مراجعة يدوية وعرض سعر مخصص.',
            ];
        }

        $product = PrintingCatalog::product($request->product_slug);
        $referenceQuantity = max(1, $product['reference_quantity'] ?? 100);
        $units = max(1, $request->quantity / $referenceQuantity);
        $price = round(
            ($product['starting_price'] ?? 0) * $units * $this->methodMultiplier($request) * $this->finishingMultiplier($request),
            2,
        );

        return [
            'pricing_type' => PrintingPricingType::Estimated,
            'estimated_price' => number_format($price, 2, '.', ''),
            'pricing_notes' => 'تقدير أولي من سعر البداية والكمية. ليس سعراً نهائياً.',
        ];
    }

    public function applyInitialSuggestion(PrintingRequest $request): void
    {
        $suggestion = $this->suggest($request);

        $request->forceFill([
            'pricing_type' => $suggestion['pricing_type'],
            'estimated_price' => $suggestion['estimated_price'],
            'pricing_notes' => $suggestion['pricing_notes'],
        ])->save();
    }

    public function requiresManualQuote(PrintingRequest $request): bool
    {
        $product = PrintingCatalog::product($request->product_slug);

        if ($product === null || $product['requires_quote']) {
            return true;
        }

        if ($request->shape === PrintingShape::Custom) {
            return true;
        }

        if ($request->printing_method === PrintingMethod::OnDemand) {
            return true;
        }

        if ($request->quantity > 10000) {
            return true;
        }

        foreach ($this->finishingValues($request) as $finishing) {
            if ($finishing === PrintingFinishing::Custom->value) {
                return true;
            }
        }

        return false;
    }

    private function methodMultiplier(PrintingRequest $request): float
    {
        return match ($request->printing_method) {
            PrintingMethod::Offset => $request->quantity >= 250 ? 0.9 : 1.15,
            default => 1.0,
        };
    }

    private function finishingMultiplier(PrintingRequest $request): float
    {
        $multiplier = 1.0;

        foreach ($this->finishingValues($request) as $finishing) {
            $multiplier += match ($finishing) {
                PrintingFinishing::Gloss->value, PrintingFinishing::Matte->value => 0.08,
                PrintingFinishing::Cut->value => 0.05,
                PrintingFinishing::DieCut->value => 0.12,
                default => 0.0,
            };
        }

        return $multiplier;
    }

    /**
     * @return list<string>
     */
    private function finishingValues(PrintingRequest $request): array
    {
        $values = [];

        foreach ($request->finishing ?? [] as $item) {
            $values[] = $item instanceof PrintingFinishing ? $item->value : (string) $item;
        }

        return $values;
    }
}
