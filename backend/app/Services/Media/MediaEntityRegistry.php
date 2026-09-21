<?php

namespace App\Services\Media;

use App\Enums\UserRole;
use App\Models\CommercialQuotation;
use App\Models\CrmCompany;
use App\Models\Invoice;
use App\Models\MarketingContent;
use App\Models\MarketingSection;
use App\Models\Meeting;
use App\Models\Package;
use App\Models\Payment;
use App\Models\PortfolioItem;
use App\Models\PrintingProduct;
use App\Models\Project;
use App\Models\Service;
use App\Models\Supplier;
use App\Models\SupplierPortfolioItem;
use App\Models\SupplierProduct;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class MediaEntityRegistry
{
    /**
     * Existing platform entities that can attach reusable Media rows.
     * Keys are morph aliases (and API entity_type values).
     *
     * @var array<string, class-string<Model>>
     */
    public const MAP = [
        'customer' => User::class,
        'company' => CrmCompany::class,
        'supplier' => Supplier::class,
        'product' => SupplierProduct::class,
        'supplier_portfolio_item' => SupplierPortfolioItem::class,
        'service' => Service::class,
        'package' => Package::class,
        'printing_product' => PrintingProduct::class,
        'portfolio' => PortfolioItem::class,
        'project' => Project::class,
        'task' => Task::class,
        'quotation' => CommercialQuotation::class,
        'invoice' => Invoice::class,
        'payment' => Payment::class,
        'meeting' => Meeting::class,
        'marketing_section' => MarketingSection::class,
        'marketing_content' => MarketingContent::class,
    ];

    /**
     * @return list<string>
     */
    public function keys(): array
    {
        return array_keys(self::MAP);
    }

    public function morphAlias(string $entityType): string
    {
        $this->assertKnown($entityType);

        return $entityType;
    }

    /**
     * @return class-string<Model>
     */
    public function modelClass(string $entityType): string
    {
        $this->assertKnown($entityType);

        return self::MAP[$entityType];
    }

    public function resolve(string $entityType, int $entityId): Model
    {
        $class = $this->modelClass($entityType);
        $owner = $class::query()->find($entityId);

        if ($owner === null) {
            throw ValidationException::withMessages([
                'entity_id' => ['Selected entity was not found.'],
            ]);
        }

        if ($entityType === 'customer') {
            /** @var User $owner */
            if ($owner->role !== UserRole::Customer) {
                throw ValidationException::withMessages([
                    'entity_id' => ['Selected customer is not available.'],
                ]);
            }
        }

        return $owner;
    }

    public function assertKnown(string $entityType): void
    {
        if (! array_key_exists($entityType, self::MAP)) {
            throw ValidationException::withMessages([
                'entity_type' => ['Unsupported entity type.'],
            ]);
        }
    }

    public function entityTypeFor(Model $owner): ?string
    {
        $morph = $owner->getMorphClass();

        if (is_string($morph) && array_key_exists($morph, self::MAP)) {
            return $morph;
        }

        foreach (self::MAP as $key => $class) {
            if ($owner instanceof $class) {
                if ($key === 'customer' && (! $owner instanceof User || $owner->role !== UserRole::Customer)) {
                    continue;
                }

                return $key;
            }
        }

        return null;
    }
}
