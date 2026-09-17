<?php

namespace App\Services;

use App\Enums\ContentStatus;
use App\Enums\SupplierVisibility;
use App\Models\Supplier;
use App\Models\SupplierProduct;
use App\Models\SupplierService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SupplierCatalogService
{
    public function duplicateProduct(Supplier $supplier, SupplierProduct $product): SupplierProduct
    {
        $this->assertProductBelongs($supplier, $product);

        $copy = $product->replicate([
            'slug',
            'sku',
            'published_at',
            'submitted_at',
            'reviewed_at',
            'reviewed_by',
            'review_notes',
        ]);
        $copy->name = $product->name.' (نسخة)';
        $copy->slug = SupplierProduct::uniqueSlug($copy->name);
        $copy->sku = $product->sku ? $product->sku.'-COPY' : null;
        $copy->status = ContentStatus::Draft;
        $copy->visibility = SupplierVisibility::Internal;
        $copy->is_featured = false;
        $copy->save();

        if ($product->relationLoaded('tags') || method_exists($product, 'tags')) {
            $copy->tags()->sync($product->tags()->pluck('tags.id')->all());
        }

        return $copy->refresh();
    }

    public function archiveProduct(Supplier $supplier, SupplierProduct $product): SupplierProduct
    {
        $this->assertProductBelongs($supplier, $product);
        $product->forceFill([
            'status' => ContentStatus::Archived,
            'visibility' => SupplierVisibility::Private,
            'is_featured' => false,
        ])->save();

        return $product->refresh();
    }

    public function duplicateService(Supplier $supplier, SupplierService $service): SupplierService
    {
        $this->assertServiceBelongs($supplier, $service);

        $copy = $service->replicate();
        $copy->name = $service->name.' (نسخة)';
        $copy->is_active = false;
        $copy->visibility = SupplierVisibility::Internal;
        $copy->save();

        if (method_exists($service, 'tags')) {
            $copy->tags()->sync($service->tags()->pluck('tags.id')->all());
        }

        return $copy->refresh();
    }

    public function archiveService(Supplier $supplier, SupplierService $service): SupplierService
    {
        $this->assertServiceBelongs($supplier, $service);
        $service->forceFill([
            'is_active' => false,
            'visibility' => SupplierVisibility::Private,
        ])->save();

        return $service->refresh();
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, skipped: int}
     */
    public function importProducts(Supplier $supplier, array $rows): array
    {
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($supplier, $rows, &$created, &$skipped): void {
            foreach ($rows as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    $skipped++;

                    continue;
                }

                $supplier->products()->create([
                    'name' => $name,
                    'slug' => SupplierProduct::uniqueSlug($name),
                    'sku' => isset($row['sku']) ? (string) $row['sku'] : null,
                    'category' => isset($row['category']) ? (string) $row['category'] : null,
                    'description' => isset($row['description']) ? (string) $row['description'] : null,
                    'short_description' => isset($row['short_description']) ? (string) $row['short_description'] : null,
                    'unit' => isset($row['unit']) ? (string) $row['unit'] : null,
                    'price' => isset($row['price']) && is_numeric($row['price']) ? $row['price'] : null,
                    'currency' => isset($row['currency']) ? Str::upper((string) $row['currency']) : 'SAR',
                    'minimum_quantity' => isset($row['minimum_quantity']) ? (int) $row['minimum_quantity'] : null,
                    'lead_time' => isset($row['lead_time']) ? (string) $row['lead_time'] : null,
                    'availability' => isset($row['availability']) ? (string) $row['availability'] : 'CONTACT',
                    'visibility' => isset($row['visibility'])
                        ? (string) $row['visibility']
                        : SupplierVisibility::Internal->value,
                    'status' => ContentStatus::Draft->value,
                    'contact_for_price' => ! isset($row['price']),
                ]);
                $created++;
            }
        });

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array{created: int, skipped: int}
     */
    public function importServices(Supplier $supplier, array $rows): array
    {
        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($supplier, $rows, &$created, &$skipped): void {
            foreach ($rows as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name === '') {
                    $skipped++;

                    continue;
                }

                $supplier->offeredServices()->create([
                    'name' => $name,
                    'category' => isset($row['category']) ? (string) $row['category'] : null,
                    'description' => isset($row['description']) ? (string) $row['description'] : null,
                    'pricing_model' => isset($row['pricing_model']) ? (string) $row['pricing_model'] : 'CUSTOM_QUOTE',
                    'minimum_price' => isset($row['minimum_price']) && is_numeric($row['minimum_price']) ? $row['minimum_price'] : null,
                    'maximum_price' => isset($row['maximum_price']) && is_numeric($row['maximum_price']) ? $row['maximum_price'] : null,
                    'currency' => isset($row['currency']) ? Str::upper((string) $row['currency']) : 'SAR',
                    'delivery_time' => isset($row['delivery_time']) ? (string) $row['delivery_time'] : null,
                    'service_area' => isset($row['service_area']) ? (string) $row['service_area'] : null,
                    'visibility' => isset($row['visibility'])
                        ? (string) $row['visibility']
                        : SupplierVisibility::Internal->value,
                    'is_active' => true,
                ]);
                $created++;
            }
        });

        return ['created' => $created, 'skipped' => $skipped];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exportProducts(Supplier $supplier): array
    {
        return $supplier->products()
            ->orderBy('id')
            ->get()
            ->map(fn (SupplierProduct $product) => [
                'id' => $product->id,
                'name' => $product->name,
                'sku' => $product->sku,
                'category' => $product->category,
                'description' => $product->description,
                'short_description' => $product->short_description,
                'unit' => $product->unit,
                'price' => $product->price,
                'currency' => $product->currency,
                'minimum_quantity' => $product->minimum_quantity,
                'lead_time' => $product->lead_time,
                'availability' => $product->availability,
                'visibility' => $product->visibility?->value,
                'status' => $product->status?->value,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exportServices(Supplier $supplier): array
    {
        return $supplier->offeredServices()
            ->orderBy('id')
            ->get()
            ->map(fn (SupplierService $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'category' => $service->category,
                'description' => $service->description,
                'pricing_model' => $service->pricing_model?->value,
                'minimum_price' => $service->minimum_price,
                'maximum_price' => $service->maximum_price,
                'currency' => $service->currency,
                'delivery_time' => $service->delivery_time,
                'service_area' => $service->service_area,
                'visibility' => $service->visibility?->value,
                'is_active' => $service->is_active,
            ])
            ->all();
    }

    /**
     * @param  list<mixed>  $rows
     * @return list<array<string, mixed>>
     */
    public function normalizeImportRows(mixed $rows): array
    {
        if (! is_array($rows) || $rows === []) {
            throw ValidationException::withMessages([
                'rows' => ['يجب إرسال صفوف للاستيراد.'],
            ]);
        }

        return array_values(array_filter($rows, fn ($row) => is_array($row)));
    }

    private function assertProductBelongs(Supplier $supplier, SupplierProduct $product): void
    {
        if ((int) $product->supplier_id !== (int) $supplier->id) {
            abort(404);
        }
    }

    private function assertServiceBelongs(Supplier $supplier, SupplierService $service): void
    {
        if ((int) $service->supplier_id !== (int) $supplier->id) {
            abort(404);
        }
    }
}
