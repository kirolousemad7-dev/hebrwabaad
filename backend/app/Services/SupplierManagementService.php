<?php

namespace App\Services;

use App\Enums\ContentReviewAction;
use App\Enums\ContentStatus;
use App\Enums\SupplierOnboardingStatus;
use App\Enums\SupplierStatus;
use App\Enums\SupplierVerificationStatus;
use App\Enums\SupplierVisibility;
use App\Models\Supplier;
use App\Models\SupplierCategory;
use App\Models\SupplierContact;
use App\Models\SupplierDocument;
use App\Models\SupplierPortfolioItem;
use App\Models\SupplierProduct;
use App\Models\SupplierService;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SupplierManagementService
{
    public function __construct(
        private readonly ContentReviewLogger $logger,
        private readonly SupplierAdminService $admin,
        private readonly PlatformNotifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Supplier>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Supplier::query()
            ->with(['categories', 'tags', 'user'])
            ->withCount(['portfolioItems', 'products', 'contacts', 'offeredServices', 'documents'])
            ->when(
                is_string($filters['q'] ?? null) && trim((string) $filters['q']) !== '',
                function ($query) use ($filters): void {
                    $term = '%'.trim((string) $filters['q']).'%';
                    $query->where(function ($inner) use ($term): void {
                        $inner->where('name', 'like', $term)
                            ->orWhere('display_name', 'like', $term)
                            ->orWhere('legal_name', 'like', $term)
                            ->orWhere('supplier_code', 'like', $term)
                            ->orWhere('location', 'like', $term)
                            ->orWhere('city', 'like', $term)
                            ->orWhere('slug', 'like', $term)
                            ->orWhere('email', 'like', $term);
                    });
                },
            )
            ->when(is_string($filters['status'] ?? null), fn ($q) => $q->where('status', $filters['status']))
            ->when(is_string($filters['verification_status'] ?? null), fn ($q) => $q->where('verification_status', $filters['verification_status']))
            ->when(is_string($filters['city'] ?? null), fn ($q) => $q->where('city', $filters['city']))
            ->when(is_string($filters['category'] ?? null), function ($q) use ($filters): void {
                $q->where(function ($inner) use ($filters): void {
                    $inner->where('category', $filters['category'])
                        ->orWhereHas('categories', fn ($c) => $c->where('slug', $filters['category'])->orWhere('name', $filters['category']));
                });
            })
            ->when(isset($filters['category_id']), fn ($q) => $q->whereHas('categories', fn ($c) => $c->whereKey($filters['category_id'])))
            ->when(isset($filters['tag_id']), fn ($q) => $q->whereHas('tags', fn ($t) => $t->whereKey($filters['tag_id'])))
            ->when(is_string($filters['service'] ?? null), function ($q) use ($filters): void {
                $term = '%'.$filters['service'].'%';
                $q->where(function ($inner) use ($term): void {
                    $inner->where('services', 'like', $term)
                        ->orWhereHas('offeredServices', fn ($s) => $s->where('name', 'like', $term));
                });
            })
            ->when(is_string($filters['country'] ?? null), fn ($q) => $q->where('country', $filters['country']))
            ->when(is_string($filters['availability'] ?? null), fn ($q) => $q->where('availability', $filters['availability']))
            ->when(is_string($filters['visibility'] ?? null), fn ($q) => $q->where('visibility', $filters['visibility']))
            ->when(is_string($filters['product'] ?? null), function ($q) use ($filters): void {
                $term = '%'.$filters['product'].'%';
                $q->whereHas('products', fn ($p) => $p->where('name', 'like', $term)->orWhere('sku', 'like', $term));
            })
            ->when(is_string($filters['tag'] ?? null), function ($q) use ($filters): void {
                $term = $filters['tag'];
                $q->whereHas('tags', fn ($t) => $t->where('slug', $term)->orWhere('name', $term));
            })
            ->when(isset($filters['price_min']) || isset($filters['price_max']), function ($q) use ($filters): void {
                $q->whereHas('products', function ($p) use ($filters): void {
                    if (isset($filters['price_min'])) {
                        $p->where('price', '>=', (float) $filters['price_min']);
                    }
                    if (isset($filters['price_max'])) {
                        $p->where('price', '<=', (float) $filters['price_max']);
                    }
                });
            })
            ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)))
            ->when(isset($filters['is_published']), fn ($q) => $q->where('is_published', filter_var($filters['is_published'], FILTER_VALIDATE_BOOLEAN)))
            ->orderByDesc('is_featured')
            ->orderBy('name')
            ->paginate(min(50, max(1, (int) ($filters['per_page'] ?? 15))));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(User $actor, array $payload): Supplier
    {
        return DB::transaction(function () use ($actor, $payload): Supplier {
            $asDraft = (bool) ($payload['save_as_draft'] ?? true);
            unset($payload['save_as_draft']);

            $categoryIds = $payload['category_ids'] ?? [];
            $tagIds = $payload['tag_ids'] ?? [];
            unset($payload['category_ids'], $payload['tag_ids']);

            $payload['created_by'] = $actor->id;
            $payload['updated_by'] = $actor->id;
            $payload['display_name'] = $payload['display_name'] ?? $payload['name'];
            $payload['legal_name'] = $payload['legal_name'] ?? $payload['name'];
            $payload['onboarding_status'] = $asDraft
                ? SupplierOnboardingStatus::Draft->value
                : SupplierOnboardingStatus::Submitted->value;
            $payload['status'] = $payload['status'] ?? SupplierStatus::Pending->value;
            $payload['verification_status'] = $payload['verification_status'] ?? SupplierVerificationStatus::Unverified->value;
            $payload['is_published'] = false;
            $payload['visibility'] = $payload['visibility'] ?? SupplierVisibility::Private->value;
            $payload['profile_status'] = ContentStatus::Draft->value;

            $supplier = $this->admin->create($actor, $payload);
            $supplier->forceFill([
                'supplier_code' => $payload['supplier_code'] ?? $this->nextSupplierCode($supplier->id),
                'legal_name' => $payload['legal_name'],
                'display_name' => $payload['display_name'],
                'whatsapp' => $payload['whatsapp'] ?? null,
                'country' => $payload['country'] ?? null,
                'city' => $payload['city'] ?? null,
                'status' => $payload['status'],
                'verification_status' => $payload['verification_status'],
                'onboarding_status' => $payload['onboarding_status'],
                'notes' => $payload['notes'] ?? null,
                'internal_notes' => $payload['internal_notes'] ?? null,
                'company_id' => $payload['company_id'] ?? null,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ])->save();

            if (is_array($categoryIds) && $categoryIds !== []) {
                $supplier->categories()->sync($categoryIds);
            }
            if (is_array($tagIds) && $tagIds !== []) {
                $supplier->tags()->sync($tagIds);
            }

            return $supplier->refresh()->load(['categories', 'tags', 'contacts', 'offeredServices']);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(User $actor, Supplier $supplier, array $payload): Supplier
    {
        return DB::transaction(function () use ($actor, $supplier, $payload): Supplier {
            $categoryIds = $payload['category_ids'] ?? null;
            $tagIds = $payload['tag_ids'] ?? null;
            unset($payload['category_ids'], $payload['tag_ids'], $payload['save_as_draft']);

            $payload['updated_by'] = $actor->id;
            if (isset($payload['name']) && ! isset($payload['display_name'])) {
                $payload['display_name'] = $payload['name'];
            }

            $this->syncLifecycleFlags($payload);
            $this->admin->update($actor, $supplier, $payload);

            if (is_array($categoryIds)) {
                $supplier->categories()->sync($categoryIds);
            }
            if (is_array($tagIds)) {
                $supplier->tags()->sync($tagIds);
            }

            return $supplier->refresh()->load(['categories', 'tags']);
        });
    }

    public function approve(User $actor, Supplier $supplier): Supplier
    {
        $supplier->forceFill([
            'status' => SupplierStatus::Active,
            'is_active' => true,
            'onboarding_status' => SupplierOnboardingStatus::Completed,
            'owner_change_request' => null,
            'updated_by' => $actor->id,
        ])->save();

        if ($supplier->user) {
            $supplier->user->forceFill(['is_active' => true])->save();
        }

        $this->logger->record($supplier, $actor, ContentReviewAction::Approved, $supplier->profile_status, $supplier->profile_status);
        $this->notifier->supplierRegistrationApproved($supplier);

        return $supplier->refresh();
    }

    public function reject(User $actor, Supplier $supplier, ?string $notes = null): Supplier
    {
        $supplier->forceFill([
            'status' => SupplierStatus::Rejected,
            'is_active' => false,
            'is_published' => false,
            'onboarding_status' => SupplierOnboardingStatus::Rejected,
            'review_notes' => $notes,
            'updated_by' => $actor->id,
        ])->save();

        $this->logger->record($supplier, $actor, ContentReviewAction::Rejected, $supplier->profile_status, $supplier->profile_status);
        $this->notifier->supplierRegistrationRejected($supplier, $notes);

        return $supplier->refresh();
    }

    public function suspend(User $actor, Supplier $supplier, ?string $notes = null): Supplier
    {
        $supplier->forceFill([
            'status' => SupplierStatus::Suspended,
            'is_active' => false,
            'notes' => $notes ?? $supplier->notes,
            'updated_by' => $actor->id,
        ])->save();

        return $supplier->refresh();
    }

    public function block(User $actor, Supplier $supplier, ?string $notes = null): Supplier
    {
        $supplier->forceFill([
            'status' => SupplierStatus::Blocked,
            'is_active' => false,
            'is_published' => false,
            'review_notes' => $notes ?? $supplier->review_notes,
            'updated_by' => $actor->id,
        ])->save();

        if ($supplier->user) {
            $supplier->user->forceFill(['is_active' => false])->save();
            $supplier->user->tokens()->delete();
        }

        return $supplier->refresh();
    }

    public function requestChanges(User $actor, Supplier $supplier, string $notes): Supplier
    {
        $supplier->forceFill([
            'onboarding_status' => SupplierOnboardingStatus::InReview,
            'owner_change_request' => $notes,
            'review_notes' => $notes,
            'updated_by' => $actor->id,
        ])->save();

        $this->notifier->supplierChangesRequested($supplier, $notes);

        return $supplier->refresh();
    }

    /**
     * @param  list<string>  $fields
     */
    public function lockFields(User $actor, Supplier $supplier, array $fields): Supplier
    {
        $allowed = array_values(array_intersect($fields, SupplierContentService::PROFILE_FIELDS));
        $supplier->forceFill([
            'locked_fields' => $allowed,
            'updated_by' => $actor->id,
        ])->save();

        return $supplier->refresh();
    }

    public function verify(User $actor, Supplier $supplier, SupplierVerificationStatus $status): Supplier
    {
        $supplier->forceFill([
            'verification_status' => $status,
            'updated_by' => $actor->id,
        ])->save();

        return $supplier->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsertContact(Supplier $supplier, array $payload, ?SupplierContact $contact = null): SupplierContact
    {
        return DB::transaction(function () use ($supplier, $payload, $contact): SupplierContact {
            if (($payload['is_primary'] ?? false) === true) {
                $supplier->contacts()->update(['is_primary' => false]);
            }

            if ($contact === null) {
                return $supplier->contacts()->create($payload);
            }

            $contact->fill($payload)->save();

            return $contact->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsertAdminProduct(Supplier $supplier, array $payload, ?SupplierProduct $product = null): SupplierProduct
    {
        $tagIds = $payload['tag_ids'] ?? null;
        unset($payload['tag_ids']);

        $payload['visibility'] = $payload['visibility'] ?? SupplierVisibility::Internal->value;
        if ($product === null) {
            $name = (string) $payload['name'];
            $payload['slug'] = $payload['slug'] ?? SupplierProduct::uniqueSlug($name);
            $payload['status'] = $payload['status'] ?? ContentStatus::Draft->value;

            $product = $supplier->products()->create($payload);
        } else {
            $product->fill($payload)->save();
            $product = $product->refresh();
        }

        if (is_array($tagIds)) {
            $product->tags()->sync($tagIds);
        }

        return $product->load('tags');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsertService(Supplier $supplier, array $payload, ?SupplierService $service = null): SupplierService
    {
        $tagIds = $payload['tag_ids'] ?? null;
        unset($payload['tag_ids']);

        $payload['visibility'] = $payload['visibility'] ?? SupplierVisibility::Internal->value;

        if ($service === null) {
            $service = $supplier->offeredServices()->create($payload);
        } else {
            $service->fill($payload)->save();
            $service = $service->refresh();
        }

        if (is_array($tagIds)) {
            $service->tags()->sync($tagIds);
        }

        return $service->load('tags');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsertAdminPortfolio(Supplier $supplier, array $payload, ?SupplierPortfolioItem $item = null): SupplierPortfolioItem
    {
        $tagIds = $payload['tag_ids'] ?? null;
        unset($payload['tag_ids']);

        $payload['visibility'] = $payload['visibility'] ?? SupplierVisibility::Internal->value;
        if ($item === null) {
            $payload['status'] = $payload['status'] ?? ContentStatus::Draft->value;

            $item = $supplier->portfolioItems()->create($payload);
        } else {
            $item->fill($payload)->save();
            $item = $item->refresh();
        }

        if (is_array($tagIds)) {
            $item->catalogTags()->sync($tagIds);
        }

        return $item->load('catalogTags');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function storeDocument(User $actor, Supplier $supplier, array $payload): SupplierDocument
    {
        $payload['uploaded_by'] = $actor->id;
        $payload['visibility'] = $payload['visibility'] ?? SupplierVisibility::Internal->value;
        // Metadata-only create path must never land on the public disk.
        $payload['disk'] = 'local';

        return $supplier->documents()->create($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsertCategory(array $payload, ?SupplierCategory $category = null): SupplierCategory
    {
        if ($category === null) {
            $payload['slug'] = $payload['slug'] ?? SupplierCategory::uniqueSlug($payload['name']);

            return SupplierCategory::query()->create($payload);
        }

        if (isset($payload['name']) && (! isset($payload['slug']) || $payload['slug'] === $category->slug)) {
            // keep slug unless explicitly changed
        }
        $category->fill($payload)->save();

        return $category->refresh();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function upsertTag(array $payload, ?Tag $tag = null): Tag
    {
        $payload['scope'] = $payload['scope'] ?? 'supplier';
        if ($tag === null) {
            $payload['slug'] = $payload['slug'] ?? Tag::uniqueSlug($payload['name']);

            return Tag::query()->create($payload);
        }

        $tag->fill($payload)->save();

        return $tag->refresh();
    }

    public function nextSupplierCode(int $id): string
    {
        return 'SUP-'.str_pad((string) $id, 5, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function syncLifecycleFlags(array &$payload): void
    {
        if (! isset($payload['status'])) {
            return;
        }

        $status = $payload['status'] instanceof SupplierStatus
            ? $payload['status']
            : SupplierStatus::tryFrom((string) $payload['status']);

        if ($status === SupplierStatus::Active) {
            $payload['is_active'] = true;
        }

        if (in_array($status, [SupplierStatus::Suspended, SupplierStatus::Rejected, SupplierStatus::Blocked], true)) {
            $payload['is_active'] = false;
        }
    }
}
