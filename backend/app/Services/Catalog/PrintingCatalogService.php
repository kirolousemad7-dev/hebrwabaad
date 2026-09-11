<?php

namespace App\Services\Catalog;

use App\Enums\UserRole;
use App\Models\PrintingProduct;
use App\Models\PrintingProductCategory;
use App\Models\PrintingProductOption;
use App\Models\User;
use App\Services\Operations\OperationsAuditLogger;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PrintingCatalogService
{
    public function __construct(
        private readonly OperationsAuditLogger $audit,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function publicProducts(): array
    {
        return PrintingProduct::query()
            ->publiclyVisible()
            ->with(['category', 'options' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (PrintingProduct $product) => $this->serializeProduct($product, public: true))
            ->all();
    }

    /**
     * @return array{categories: list<array<string, mixed>>, products: list<array<string, mixed>>, options: list<array<string, mixed>>}
     */
    public function manageIndex(User $actor): array
    {
        $this->assertCanManage($actor);

        return [
            'categories' => PrintingProductCategory::query()->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (PrintingProductCategory $category) => [
                    'id' => $category->id,
                    'slug' => $category->slug,
                    'name_ar' => $category->name_ar,
                    'name_en' => $category->name_en,
                    'is_active' => $category->is_active,
                    'sort_order' => $category->sort_order,
                ])->all(),
            'products' => PrintingProduct::query()
                ->with(['category', 'options'])
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (PrintingProduct $product) => $this->serializeProduct($product, public: false))
                ->all(),
            'options' => PrintingProductOption::query()->orderBy('type')->orderBy('sort_order')->get()
                ->map(fn (PrintingProductOption $option) => [
                    'id' => $option->id,
                    'type' => $option->type,
                    'slug' => $option->slug,
                    'name_ar' => $option->name_ar,
                    'name_en' => $option->name_en,
                    'is_active' => $option->is_active,
                    'sort_order' => $option->sort_order,
                ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function upsertProduct(User $actor, array $data, ?PrintingProduct $product = null): array
    {
        $this->assertCanManage($actor);

        $pricingMode = strtoupper((string) ($data['pricing_mode'] ?? 'QUOTE'));
        if (! in_array($pricingMode, ['FIXED', 'STARTING_FROM', 'QUOTE'], true)) {
            throw ValidationException::withMessages([
                'pricing_mode' => ['Invalid pricing mode.'],
            ]);
        }

        $nameAr = trim(strip_tags((string) ($data['name_ar'] ?? '')));
        if ($nameAr === '') {
            throw ValidationException::withMessages(['name_ar' => ['Required.']]);
        }

        $slug = trim((string) ($data['slug'] ?? '')) ?: Str::slug($nameAr);
        if ($slug === '') {
            $slug = 'product-'.Str::lower(Str::random(6));
        }

        $attributes = [
            'category_id' => isset($data['category_id']) ? (int) $data['category_id'] : null,
            'slug' => $slug,
            'name_ar' => $nameAr,
            'name_en' => isset($data['name_en']) ? trim(strip_tags((string) $data['name_en'])) : null,
            'short_description' => isset($data['short_description']) ? trim(strip_tags((string) $data['short_description'])) : null,
            'description' => isset($data['description']) ? trim(strip_tags((string) $data['description'])) : null,
            'image_path' => isset($data['image_path']) ? (string) $data['image_path'] : ($product?->image_path),
            'pricing_mode' => $pricingMode,
            'starting_price' => $pricingMode === 'QUOTE' ? null : ($data['starting_price'] ?? null),
            'currency' => strtoupper((string) ($data['currency'] ?? 'SAR')),
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'is_public' => array_key_exists('is_public', $data) ? (bool) $data['is_public'] : true,
            'is_featured' => array_key_exists('is_featured', $data) ? (bool) $data['is_featured'] : false,
            'allows_design_and_print' => array_key_exists('allows_design_and_print', $data) ? (bool) $data['allows_design_and_print'] : true,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];

        if ($product === null) {
            $product = PrintingProduct::query()->create($attributes);
            $action = 'printing_catalog.product_created';
        } else {
            $product->update($attributes);
            $action = 'printing_catalog.product_updated';
        }

        if (isset($data['option_ids']) && is_array($data['option_ids'])) {
            $ids = array_values(array_unique(array_map('intval', $data['option_ids'])));
            $product->options()->sync($ids);
        }

        $this->audit->log($actor, $action, $product, [
            'slug' => $product->slug,
            'pricing_mode' => $product->pricing_mode,
        ]);

        return $this->serializeProduct($product->fresh(['category', 'options']), public: false);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function upsertCategory(User $actor, array $data, ?PrintingProductCategory $category = null): array
    {
        $this->assertCanManage($actor);
        $nameAr = trim(strip_tags((string) ($data['name_ar'] ?? '')));
        if ($nameAr === '') {
            throw ValidationException::withMessages(['name_ar' => ['Required.']]);
        }
        $slug = trim((string) ($data['slug'] ?? '')) ?: Str::slug($nameAr);
        $attributes = [
            'slug' => $slug,
            'name_ar' => $nameAr,
            'name_en' => isset($data['name_en']) ? trim(strip_tags((string) $data['name_en'])) : null,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
        if ($category === null) {
            $category = PrintingProductCategory::query()->create($attributes);
        } else {
            $category->update($attributes);
        }

        return [
            'id' => $category->id,
            'slug' => $category->slug,
            'name_ar' => $category->name_ar,
            'name_en' => $category->name_en,
            'is_active' => $category->is_active,
            'sort_order' => $category->sort_order,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function upsertOption(User $actor, array $data, ?PrintingProductOption $option = null): array
    {
        $this->assertCanManage($actor);
        $type = strtolower((string) ($data['type'] ?? ''));
        if (! in_array($type, ['material', 'size', 'finishing', 'method', 'color', 'quantity'], true)) {
            throw ValidationException::withMessages(['type' => ['Invalid option type.']]);
        }
        $nameAr = trim(strip_tags((string) ($data['name_ar'] ?? '')));
        if ($nameAr === '') {
            throw ValidationException::withMessages(['name_ar' => ['Required.']]);
        }
        $slug = trim((string) ($data['slug'] ?? '')) ?: Str::slug($type.'-'.$nameAr);
        $attributes = [
            'type' => $type,
            'slug' => $slug,
            'name_ar' => $nameAr,
            'name_en' => isset($data['name_en']) ? trim(strip_tags((string) $data['name_en'])) : null,
            'is_active' => array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
        ];
        if ($option === null) {
            $option = PrintingProductOption::query()->create($attributes);
        } else {
            $option->update($attributes);
        }

        return [
            'id' => $option->id,
            'type' => $option->type,
            'slug' => $option->slug,
            'name_ar' => $option->name_ar,
            'name_en' => $option->name_en,
            'is_active' => $option->is_active,
            'sort_order' => $option->sort_order,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeProduct(PrintingProduct $product, bool $public): array
    {
        $image = $product->image_path;
        if ($image && ! str_starts_with($image, '/') && ! str_starts_with($image, 'http')) {
            $image = Storage::disk('public')->url($image);
        }

        $row = [
            'id' => $product->id,
            'slug' => $product->slug,
            'name_ar' => $product->name_ar,
            'name_en' => $product->name_en,
            'short_description' => $product->short_description,
            'description' => $public ? null : $product->description,
            'image_url' => $image,
            'pricing_mode' => $product->pricing_mode,
            'starting_price' => $product->pricing_mode === 'QUOTE' ? null : $product->starting_price,
            'currency' => $product->currency,
            'is_featured' => $product->is_featured,
            'allows_design_and_print' => $product->allows_design_and_print,
            'sort_order' => $product->sort_order,
            'category' => $product->category ? [
                'id' => $product->category->id,
                'slug' => $product->category->slug,
                'name_ar' => $product->category->name_ar,
            ] : null,
            'options' => $product->options->map(fn (PrintingProductOption $option) => [
                'id' => $option->id,
                'type' => $option->type,
                'slug' => $option->slug,
                'name_ar' => $option->name_ar,
            ])->values()->all(),
        ];

        if (! $public) {
            $row['description'] = $product->description;
            $row['is_active'] = $product->is_active;
            $row['is_public'] = $product->is_public;
            $row['image_path'] = $product->image_path;
            $row['category_id'] = $product->category_id;
            $row['option_ids'] = $product->options->pluck('id')->all();
        }

        return $row;
    }

    private function assertCanManage(User $actor): void
    {
        $role = $actor->role instanceof UserRole ? $actor->role : UserRole::tryFrom((string) $actor->role);
        if ($role === null || ! $role->canManageCatalog()) {
            abort(403);
        }
    }
}
