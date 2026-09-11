<?php

namespace App\Http\Resources;

use App\Models\PortfolioItem;
use App\Services\Portfolio\PortfolioShowcaseService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PortfolioItem
 */
class PortfolioItemResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var PortfolioShowcaseService $showcase */
        $showcase = app(PortfolioShowcaseService::class);

        if ($request->user()?->role?->canManageCatalog()) {
            return $showcase->staffPayload($this->resource);
        }

        return $showcase->listingPayload($this->resource);
    }
}
