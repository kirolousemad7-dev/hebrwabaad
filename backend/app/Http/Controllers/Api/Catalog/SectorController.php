<?php

namespace App\Http\Controllers\Api\Catalog;

use App\Http\Controllers\Controller;
use App\Models\Sector;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SectorController extends Controller
{
    /**
     * Public DATA SOURCE aliases that keep the existing 12 sector slugs.
     *
     * @var array<string, string>
     */
    public const SLUG_ALIASES = [
        'b2b-industrial' => 'industry-b2b',
        'health-beauty' => 'health-clinics',
    ];

    public function index(Request $request): JsonResponse
    {
        $sectors = Sector::query()
            ->active()
            ->public()
            ->orderBy('sort_order')
            ->orderBy('name_ar')
            ->get()
            ->map(fn (Sector $sector) => $this->summary($sector));

        return ApiResponse::success($sectors->values()->all());
    }

    public function show(Request $request, string $slug): JsonResponse
    {
        $resolved = self::SLUG_ALIASES[$slug] ?? $slug;

        $model = Sector::query()
            ->active()
            ->public()
            ->where('slug', $resolved)
            ->with([
                'services' => fn ($query) => $query->active()->public()->orderBy('name'),
                'packages' => fn ($query) => $query->active()->public()->orderBy('sort_order')->orderBy('name'),
                'portfolioItems' => fn ($query) => $query->published(),
            ])
            ->firstOrFail();

        return ApiResponse::success([
            ...$this->summary($model),
            'services' => $model->services->map(fn ($service) => [
                'id' => $service->id,
                'name' => $service->name,
                'slug' => $service->slug,
                'summary' => $service->summary,
                'category' => $service->category->value,
                'base_price' => $service->base_price,
                'currency' => $service->currency,
                'pricing_mode' => $service->pricingMode()->value,
                'is_chargeable' => $service->isChargeable(),
                'duration_days' => $service->duration_days,
            ])->values()->all(),
            'packages' => $model->packages->map(fn ($package) => [
                'id' => $package->id,
                'name' => $package->name,
                'slug' => $package->slug,
                'description' => $package->description,
                'final_price' => $package->finalPrice(),
                'currency' => $package->currency,
                'pricing_mode' => $package->pricingMode()->value,
                'is_chargeable' => $package->isChargeable(),
            ])->values()->all(),
            'case_studies' => $model->portfolioItems->map(fn ($item) => [
                'id' => $item->id,
                'slug' => $item->slug,
                'title' => $item->title,
                'description' => $item->short_description ?: $item->description,
                'image_url' => $item->image_url,
                'challenge' => $item->challenge,
                'solution' => $item->solution,
                'results' => $item->results,
            ])->values()->all(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Sector $sector): array
    {
        return [
            'id' => $sector->id,
            'name_ar' => $sector->name_ar,
            'name_en' => $sector->name_en,
            'slug' => $sector->slug,
            'description' => $sector->description,
            'needs' => $sector->needs ?? [],
            'cover_image' => $sector->cover_image,
            'sort_order' => $sector->sort_order,
        ];
    }
}
