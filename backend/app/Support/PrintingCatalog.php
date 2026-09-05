<?php

namespace App\Support;

/**
 * Allowed printing products and the minimum pricing metadata needed
 * for Task 14 estimates. Full catalog copy remains on the frontend.
 */
class PrintingCatalog
{
    /**
     * @return list<array{slug: string, starting_price: float, requires_quote: bool, reference_quantity: int}>
     */
    public static function products(): array
    {
        return [
            ['slug' => 'standard-business-cards', 'starting_price' => 85.0, 'requires_quote' => false, 'reference_quantity' => 100],
            ['slug' => 'premium-business-cards', 'starting_price' => 180.0, 'requires_quote' => false, 'reference_quantity' => 100],
            ['slug' => 'luxury-business-cards', 'starting_price' => 320.0, 'requires_quote' => false, 'reference_quantity' => 100],
            ['slug' => 'a5-flyers', 'starting_price' => 120.0, 'requires_quote' => false, 'reference_quantity' => 100],
            ['slug' => 'a4-flyers', 'starting_price' => 180.0, 'requires_quote' => false, 'reference_quantity' => 100],
            ['slug' => 'premium-flyers', 'starting_price' => 280.0, 'requires_quote' => false, 'reference_quantity' => 50],
            ['slug' => 'die-cut-stickers', 'starting_price' => 90.0, 'requires_quote' => false, 'reference_quantity' => 100],
            ['slug' => 'round-stickers', 'starting_price' => 70.0, 'requires_quote' => false, 'reference_quantity' => 100],
            ['slug' => 'product-labels', 'starting_price' => 110.0, 'requires_quote' => false, 'reference_quantity' => 100],
            ['slug' => 'folding-boxes', 'starting_price' => 250.0, 'requires_quote' => false, 'reference_quantity' => 25],
            ['slug' => 'product-boxes', 'starting_price' => 320.0, 'requires_quote' => false, 'reference_quantity' => 25],
            ['slug' => 'gift-boxes', 'starting_price' => 400.0, 'requires_quote' => false, 'reference_quantity' => 25],
            ['slug' => 'paper-bags', 'starting_price' => 150.0, 'requires_quote' => false, 'reference_quantity' => 50],
            ['slug' => 'luxury-shopping-bags', 'starting_price' => 280.0, 'requires_quote' => false, 'reference_quantity' => 50],
            ['slug' => 'custom-printed-bags', 'starting_price' => 220.0, 'requires_quote' => false, 'reference_quantity' => 50],
            ['slug' => 'product-packaging', 'starting_price' => 350.0, 'requires_quote' => false, 'reference_quantity' => 25],
            ['slug' => 'food-packaging', 'starting_price' => 380.0, 'requires_quote' => false, 'reference_quantity' => 25],
            ['slug' => 'branded-packaging', 'starting_price' => 450.0, 'requires_quote' => false, 'reference_quantity' => 25],
            ['slug' => 'a3-posters', 'starting_price' => 90.0, 'requires_quote' => false, 'reference_quantity' => 20],
            ['slug' => 'a2-posters', 'starting_price' => 140.0, 'requires_quote' => false, 'reference_quantity' => 20],
            ['slug' => 'large-format-posters', 'starting_price' => 280.0, 'requires_quote' => false, 'reference_quantity' => 10],
            ['slug' => 'custom-printed-product', 'starting_price' => 500.0, 'requires_quote' => true, 'reference_quantity' => 1],
            ['slug' => 'custom-promotional-product', 'starting_price' => 450.0, 'requires_quote' => true, 'reference_quantity' => 1],
        ];
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_column(self::products(), 'slug');
    }

    public static function hasSlug(string $slug): bool
    {
        return in_array($slug, self::slugs(), true);
    }

    /**
     * @return array{slug: string, starting_price: float, requires_quote: bool, reference_quantity: int}|null
     */
    public static function product(string $slug): ?array
    {
        foreach (self::products() as $product) {
            if ($product['slug'] === $slug) {
                return $product;
            }
        }

        return null;
    }
}
