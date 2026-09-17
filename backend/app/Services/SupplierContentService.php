<?php

namespace App\Services;

use App\Enums\ContentReviewAction;
use App\Enums\ContentStatus;
use App\Enums\SupplierContentType;
use App\Enums\SupplierOnboardingStatus;
use App\Enums\SupplierStatus;
use App\Enums\SupplierVisibility;
use App\Enums\UserRole;
use App\Exceptions\ContentWorkflowException;
use App\Models\Supplier;
use App\Models\SupplierPortfolioItem;
use App\Models\SupplierProduct;
use App\Models\SupplierProfileVersion;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class SupplierContentService
{
    /**
     * @var list<string>
     */
    public const PROFILE_FIELDS = [
        'name',
        'logo',
        'cover_image',
        'short_description',
        'description',
        'phone',
        'email',
        'whatsapp',
        'website',
        'location',
        'country',
        'city',
        'address',
        'category',
        'contact_person',
        'services',
        'specialties',
        'years_experience',
        'min_order_info',
        'brand_colors',
        'brand_description',
        'availability',
        'delivery_time',
        'service_areas',
        'certifications',
        'seo_title',
        'seo_description',
        'og_title',
        'og_description',
        'og_image',
        'canonical_url',
        'robots',
    ];

    public function __construct(
        private readonly ContentReviewLogger $logger,
        private readonly PlatformNotifier $notifier,
    ) {}

    public function supplierFor(User $user): Supplier
    {
        $supplier = Supplier::query()->where('user_id', $user->id)->first();

        if ($supplier === null) {
            throw new ContentWorkflowException('لا يوجد ملف مورد مرتبط بهذا الحساب.', 404);
        }

        return $supplier;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateProfile(User $user, array $payload): Supplier
    {
        $supplier = $this->supplierFor($user);
        $this->assertNotBlocked($supplier);
        $safe = $this->onlyProfile($payload);
        $safe = $this->withoutLockedFields($supplier, $safe);

        if ($supplier->isPubliclyVisible()) {
            $version = $supplier->pendingProfileVersion ?? new SupplierProfileVersion([
                'supplier_id' => $supplier->id,
                'status' => ContentStatus::Draft,
            ]);
            $version->forceFill([
                'payload' => array_merge($this->liveProfilePayload($supplier), $safe),
                'status' => ContentStatus::Draft,
                'submitted_by' => $user->id,
                'review_notes' => null,
            ])->save();

            return $supplier->fresh(['pendingProfileVersion']) ?? $supplier;
        }

        $supplier->fill($safe)->save();
        if ($supplier->profile_status === ContentStatus::Published) {
            $supplier->forceFill(['profile_status' => ContentStatus::Draft])->save();
        }

        return $supplier->refresh();
    }

    public function submitProfile(User $user): Supplier
    {
        $supplier = $this->supplierFor($user);
        $version = $supplier->pendingProfileVersion;

        if ($version !== null) {
            $from = $version->status;
            if (! $from->contributorCanSubmit()) {
                throw new ContentWorkflowException('لا يمكن إرسال التعديلات في هذه الحالة.');
            }
            $version->forceFill([
                'status' => ContentStatus::Submitted,
                'submitted_at' => now(),
                'submitted_by' => $user->id,
            ])->save();
            $this->logger->record($version, $user, ContentReviewAction::Submitted, $from, ContentStatus::Submitted);
            $this->notifier->supplierContentSubmitted($supplier, SupplierContentType::ProfileVersion, $supplier->name);

            return $supplier->fresh(['pendingProfileVersion']) ?? $supplier;
        }

        if (! $supplier->profile_status->contributorCanSubmit()) {
            throw new ContentWorkflowException('لا يمكن إرسال الملف في هذه الحالة.');
        }

        $from = $supplier->profile_status;
        $supplier->forceFill(['profile_status' => ContentStatus::Submitted])->save();
        $this->logger->record($supplier, $user, ContentReviewAction::Submitted, $from, ContentStatus::Submitted);
        $this->notifier->supplierContentSubmitted($supplier, SupplierContentType::Profile, $supplier->name);

        return $supplier->refresh();
    }

    public function approveProfile(User $reviewer, Supplier $supplier): Supplier
    {
        $this->assertReviewer($reviewer);
        $version = $supplier->pendingProfileVersion;

        if ($version !== null) {
            if (! $version->status->reviewerCanModerate()) {
                throw new ContentWorkflowException('لا توجد تعديلات بانتظار المراجعة.');
            }
            $from = $version->status;
            $supplier->fill($this->onlyProfile($version->payload ?? []));
            $supplier->forceFill([
                'is_published' => true,
                'is_active' => true,
                'status' => SupplierStatus::Active,
                'onboarding_status' => SupplierOnboardingStatus::Completed,
                'profile_status' => ContentStatus::Published,
                'visibility' => SupplierVisibility::Public,
                'published_at' => now(),
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_notes' => null,
            ])->save();
            $version->forceFill([
                'status' => ContentStatus::Published,
                'published_at' => now(),
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ])->save();
            $this->logger->record($version, $reviewer, ContentReviewAction::Published, $from, ContentStatus::Published);
            $this->notifier->supplierContentPublished($supplier, SupplierContentType::Profile, $supplier->name);

            return $supplier->refresh();
        }

        if (! in_array($supplier->profile_status, [ContentStatus::Submitted, ContentStatus::UnderReview, ContentStatus::Approved, ContentStatus::Draft], true)
            && $supplier->is_published) {
            throw new ContentWorkflowException('لا يمكن نشر الملف في هذه الحالة.');
        }

        $from = $supplier->profile_status;
        $supplier->forceFill([
            'is_published' => true,
            'is_active' => true,
            'status' => SupplierStatus::Active,
            'onboarding_status' => SupplierOnboardingStatus::Completed,
            'profile_status' => ContentStatus::Published,
            'visibility' => SupplierVisibility::Public,
            'published_at' => now(),
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => null,
        ])->save();
        $this->logger->record($supplier, $reviewer, ContentReviewAction::Published, $from, ContentStatus::Published);
        $this->notifier->supplierContentPublished($supplier, SupplierContentType::Profile, $supplier->name);

        return $supplier->refresh();
    }

    public function rejectProfile(User $reviewer, Supplier $supplier, string $notes): Supplier
    {
        return $this->returnProfile($reviewer, $supplier, ContentStatus::Rejected, ContentReviewAction::Rejected, $notes);
    }

    public function requestProfileChanges(User $reviewer, Supplier $supplier, string $notes): Supplier
    {
        return $this->returnProfile($reviewer, $supplier, ContentStatus::ChangesRequested, ContentReviewAction::ChangesRequested, $notes);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createPortfolioItem(User $user, array $payload): SupplierPortfolioItem
    {
        $supplier = $this->supplierFor($user);
        $item = SupplierPortfolioItem::query()->create([
            ...$this->portfolioPayload($payload),
            'supplier_id' => $supplier->id,
            'status' => ContentStatus::Draft,
            'is_active' => false,
        ]);
        $this->logger->record($item, $user, ContentReviewAction::Created, null, ContentStatus::Draft);

        return $item;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updatePortfolioItem(User $user, SupplierPortfolioItem $item, array $payload): SupplierPortfolioItem
    {
        $this->assertSupplierOwns($user, $item->supplier_id);

        if (! $item->status->contributorCanEdit()) {
            throw new ContentWorkflowException('لا يمكن تعديل هذا العمل في هذه الحالة.');
        }

        $item->fill($this->portfolioPayload($payload))->save();

        return $item->refresh();
    }

    public function submitPortfolioItem(User $user, SupplierPortfolioItem $item): SupplierPortfolioItem
    {
        $this->assertSupplierOwns($user, $item->supplier_id);

        if (! $item->status->contributorCanSubmit()) {
            throw new ContentWorkflowException('لا يمكن إرسال العمل في هذه الحالة.');
        }

        $from = $item->status;
        $item->forceFill([
            'status' => ContentStatus::Submitted,
            'submitted_at' => now(),
            'review_notes' => null,
        ])->save();
        $this->logger->record($item, $user, ContentReviewAction::Submitted, $from, ContentStatus::Submitted);
        $this->notifier->supplierContentSubmitted($item->supplier, SupplierContentType::Portfolio, $item->title);

        return $item->refresh();
    }

    public function approvePortfolioItem(User $reviewer, SupplierPortfolioItem $item): SupplierPortfolioItem
    {
        $this->assertReviewer($reviewer);
        $this->assertReviewable($item->status);
        $from = $item->status;
        $item->forceFill([
            'status' => ContentStatus::Published,
            'visibility' => SupplierVisibility::Public,
            'is_active' => true,
            'published_at' => now(),
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => null,
        ])->save();
        $this->logger->record($item, $reviewer, ContentReviewAction::Published, $from, ContentStatus::Published);
        $this->notifier->supplierContentPublished($item->supplier, SupplierContentType::Portfolio, $item->title);

        return $item->refresh();
    }

    public function rejectPortfolioItem(User $reviewer, SupplierPortfolioItem $item, string $notes): SupplierPortfolioItem
    {
        return $this->returnItem($reviewer, $item, ContentStatus::Rejected, ContentReviewAction::Rejected, $notes, SupplierContentType::Portfolio);
    }

    public function requestPortfolioChanges(User $reviewer, SupplierPortfolioItem $item, string $notes): SupplierPortfolioItem
    {
        return $this->returnItem($reviewer, $item, ContentStatus::ChangesRequested, ContentReviewAction::ChangesRequested, $notes, SupplierContentType::Portfolio);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function createProduct(User $user, array $payload): SupplierProduct
    {
        $supplier = $this->supplierFor($user);
        $product = SupplierProduct::query()->create([
            ...$this->productPayload($payload, $supplier),
            'supplier_id' => $supplier->id,
            'status' => ContentStatus::Draft,
            'is_featured' => false,
        ]);
        $this->logger->record($product, $user, ContentReviewAction::Created, null, ContentStatus::Draft);

        return $product;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function updateProduct(User $user, SupplierProduct $product, array $payload): SupplierProduct
    {
        $this->assertSupplierOwns($user, $product->supplier_id);

        if (! $product->status->contributorCanEdit()) {
            throw new ContentWorkflowException('لا يمكن تعديل المنتج في هذه الحالة.');
        }

        $product->fill($this->productPayload($payload, $product->supplier, $product->id))->save();

        return $product->refresh();
    }

    public function submitProduct(User $user, SupplierProduct $product): SupplierProduct
    {
        $this->assertSupplierOwns($user, $product->supplier_id);

        if (! $product->status->contributorCanSubmit()) {
            throw new ContentWorkflowException('لا يمكن إرسال المنتج في هذه الحالة.');
        }

        $from = $product->status;
        $product->forceFill([
            'status' => ContentStatus::Submitted,
            'submitted_at' => now(),
            'review_notes' => null,
        ])->save();
        $this->logger->record($product, $user, ContentReviewAction::Submitted, $from, ContentStatus::Submitted);
        $this->notifier->supplierContentSubmitted($product->supplier, SupplierContentType::Product, $product->name);

        return $product->refresh();
    }

    public function approveProduct(User $reviewer, SupplierProduct $product): SupplierProduct
    {
        $this->assertReviewer($reviewer);
        $this->assertReviewable($product->status);
        $from = $product->status;
        $product->forceFill([
            'status' => ContentStatus::Published,
            'visibility' => SupplierVisibility::Public,
            'published_at' => now(),
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_notes' => null,
        ])->save();
        $this->logger->record($product, $reviewer, ContentReviewAction::Published, $from, ContentStatus::Published);
        $this->notifier->supplierContentPublished($product->supplier, SupplierContentType::Product, $product->name);

        return $product->refresh();
    }

    public function rejectProduct(User $reviewer, SupplierProduct $product, string $notes): SupplierProduct
    {
        return $this->returnProduct($reviewer, $product, ContentStatus::Rejected, ContentReviewAction::Rejected, $notes);
    }

    public function requestProductChanges(User $reviewer, SupplierProduct $product, string $notes): SupplierProduct
    {
        return $this->returnProduct($reviewer, $product, ContentStatus::ChangesRequested, ContentReviewAction::ChangesRequested, $notes);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{items: list<array<string, mixed>>, counts: array<string, int>}
     */
    public function dashboard(User $user, array $filters): array
    {
        $supplier = $this->supplierFor($user)->load(['pendingProfileVersion', 'portfolioItems', 'products']);
        $items = $this->contentRows($supplier);
        $status = $filters['status'] ?? null;

        if (is_string($status) && $status !== '') {
            $items = array_values(array_filter($items, fn (array $row) => $row['status'] === $status));
        }

        $counts = [
            'drafts' => 0,
            'under_review' => 0,
            'published' => 0,
            'needs_changes' => 0,
        ];

        foreach ($this->contentRows($supplier) as $row) {
            $value = ContentStatus::from($row['status']);
            if ($value === ContentStatus::Draft) {
                $counts['drafts']++;
            } elseif (in_array($value, [ContentStatus::Submitted, ContentStatus::UnderReview], true)) {
                $counts['under_review']++;
            } elseif ($value === ContentStatus::Published) {
                $counts['published']++;
            } elseif (in_array($value, [ContentStatus::ChangesRequested, ContentStatus::Rejected], true)) {
                $counts['needs_changes']++;
            }
        }

        return ['items' => $items, 'counts' => $counts];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, mixed>
     */
    public function reviewInbox(array $filters): LengthAwarePaginator
    {
        $type = $filters['type'] ?? null;

        $profile = Supplier::query()
            ->with('user')
            ->whereIn('profile_status', [ContentStatus::Submitted, ContentStatus::UnderReview])
            ->get()
            ->map(fn (Supplier $supplier) => [
                'id' => $supplier->id,
                'type' => SupplierContentType::Profile->value,
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'title' => $supplier->name,
                'status' => $supplier->profile_status->value,
                'submitted_at' => $supplier->updated_at?->toIso8601String(),
            ]);

        $versions = SupplierProfileVersion::query()
            ->with('supplier')
            ->whereIn('status', [ContentStatus::Submitted, ContentStatus::UnderReview])
            ->get()
            ->map(fn (SupplierProfileVersion $version) => [
                'id' => $version->id,
                'type' => SupplierContentType::ProfileVersion->value,
                'supplier_id' => $version->supplier_id,
                'supplier_name' => $version->supplier?->name,
                'title' => 'تعديلات ملف '.$version->supplier?->name,
                'status' => $version->status->value,
                'submitted_at' => $version->submitted_at?->toIso8601String(),
            ]);

        $portfolio = SupplierPortfolioItem::query()
            ->with('supplier')
            ->whereIn('status', [ContentStatus::Submitted, ContentStatus::UnderReview])
            ->get()
            ->map(fn (SupplierPortfolioItem $item) => [
                'id' => $item->id,
                'type' => SupplierContentType::Portfolio->value,
                'supplier_id' => $item->supplier_id,
                'supplier_name' => $item->supplier?->name,
                'title' => $item->title,
                'status' => $item->status->value,
                'submitted_at' => $item->submitted_at?->toIso8601String(),
            ]);

        $products = SupplierProduct::query()
            ->with('supplier')
            ->whereIn('status', [ContentStatus::Submitted, ContentStatus::UnderReview])
            ->get()
            ->map(fn (SupplierProduct $product) => [
                'id' => $product->id,
                'type' => SupplierContentType::Product->value,
                'supplier_id' => $product->supplier_id,
                'supplier_name' => $product->supplier?->name,
                'title' => $product->name,
                'status' => $product->status->value,
                'submitted_at' => $product->submitted_at?->toIso8601String(),
            ]);

        $rows = $profile->concat($versions)->concat($portfolio)->concat($products)->values();

        if (is_string($type) && $type !== '') {
            $rows = $rows->where('type', $type)->values();
        }

        $perPage = min(50, max(1, (int) ($filters['per_page'] ?? 15)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $slice = $rows->forPage($page, $perPage)->values();

        return new \Illuminate\Pagination\LengthAwarePaginator(
            $slice,
            $rows->count(),
            $perPage,
            $page,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function contentRows(Supplier $supplier): array
    {
        $rows = [];
        $rows[] = [
            'id' => $supplier->id,
            'type' => SupplierContentType::Profile->value,
            'title' => $supplier->name,
            'status' => $supplier->profile_status->value,
            'updated_at' => $supplier->updated_at?->toIso8601String(),
            'review_notes' => $supplier->review_notes,
        ];

        if ($supplier->pendingProfileVersion) {
            $version = $supplier->pendingProfileVersion;
            $rows[] = [
                'id' => $version->id,
                'type' => SupplierContentType::ProfileVersion->value,
                'title' => 'تعديلات الملف الشخصي',
                'status' => $version->status->value,
                'updated_at' => $version->updated_at?->toIso8601String(),
                'review_notes' => $version->review_notes,
            ];
        }

        foreach ($supplier->portfolioItems as $item) {
            $rows[] = [
                'id' => $item->id,
                'type' => SupplierContentType::Portfolio->value,
                'title' => $item->title,
                'status' => $item->status->value,
                'updated_at' => $item->updated_at?->toIso8601String(),
                'review_notes' => $item->review_notes,
            ];
        }

        foreach ($supplier->products as $product) {
            $rows[] = [
                'id' => $product->id,
                'type' => SupplierContentType::Product->value,
                'title' => $product->name,
                'status' => $product->status->value,
                'updated_at' => $product->updated_at?->toIso8601String(),
                'review_notes' => $product->review_notes,
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function onlyProfile(array $payload): array
    {
        return array_intersect_key($payload, array_flip(self::PROFILE_FIELDS));
    }

    /**
     * @return array<string, mixed>
     */
    private function liveProfilePayload(Supplier $supplier): array
    {
        $data = [];
        foreach (self::PROFILE_FIELDS as $field) {
            $data[$field] = $supplier->{$field};
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function portfolioPayload(array $payload): array
    {
        return [
            'title' => $payload['title'],
            'description' => $payload['description'] ?? '',
            'image' => $payload['image'] ?? '/brand/logo.png',
            'gallery' => array_values($payload['gallery'] ?? []),
            'category' => $payload['category'] ?? '',
            'tags' => array_values($payload['tags'] ?? []),
            'external_url' => $payload['external_url'] ?? null,
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function productPayload(array $payload, Supplier $supplier, ?int $ignoreId = null): array
    {
        $name = (string) $payload['name'];
        $slug = isset($payload['slug']) && is_string($payload['slug']) && $payload['slug'] !== ''
            ? $payload['slug']
            : SupplierProduct::uniqueSlug($name, $ignoreId);

        while (
            SupplierProduct::query()
                ->where('supplier_id', $supplier->id)
                ->where('slug', $slug)
                ->when($ignoreId !== null, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug .= '-'.Str::lower(Str::random(4));
        }

        return [
            'name' => $name,
            'slug' => $slug,
            'short_description' => $payload['short_description'] ?? null,
            'description' => $payload['description'] ?? null,
            'images' => array_values($payload['images'] ?? []),
            'category' => $payload['category'] ?? null,
            'specifications' => $payload['specifications'] ?? null,
            'variants' => $payload['variants'] ?? null,
            'price' => $payload['contact_for_price'] ?? true ? null : ($payload['price'] ?? null),
            'currency' => $payload['currency'] ?? 'SAR',
            'contact_for_price' => (bool) ($payload['contact_for_price'] ?? true),
            'availability' => $payload['availability'] ?? 'CONTACT',
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
            'seo_title' => $payload['seo_title'] ?? null,
            'seo_description' => $payload['seo_description'] ?? null,
            'og_title' => $payload['og_title'] ?? null,
            'og_description' => $payload['og_description'] ?? null,
            'og_image' => $payload['og_image'] ?? null,
            'canonical_url' => $payload['canonical_url'] ?? null,
            'robots' => $payload['robots'] ?? null,
        ];
    }

    private function returnProfile(
        User $reviewer,
        Supplier $supplier,
        ContentStatus $next,
        ContentReviewAction $action,
        string $notes,
    ): Supplier {
        $this->assertReviewer($reviewer);
        $version = $supplier->pendingProfileVersion;

        if ($version !== null) {
            if (! in_array($version->status, [ContentStatus::Submitted, ContentStatus::UnderReview], true)) {
                throw new ContentWorkflowException('لا يمكن مراجعة التعديلات في هذه الحالة.');
            }
            $from = $version->status;
            $version->forceFill([
                'status' => $next,
                'review_notes' => $notes,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ])->save();
            $this->logger->record($version, $reviewer, $action, $from, $next, $notes);
        } else {
            if (! in_array($supplier->profile_status, [ContentStatus::Submitted, ContentStatus::UnderReview], true)) {
                throw new ContentWorkflowException('لا يمكن مراجعة الملف في هذه الحالة.');
            }
            $from = $supplier->profile_status;
            $supplier->forceFill([
                'profile_status' => $next,
                'review_notes' => $notes,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
            ])->save();
            $this->logger->record($supplier, $reviewer, $action, $from, $next, $notes);
        }

        if ($next === ContentStatus::Rejected) {
            $this->notifier->supplierContentRejected($supplier, SupplierContentType::Profile, $supplier->name, $notes);
        } else {
            $this->notifier->supplierContentChangesRequested($supplier, SupplierContentType::Profile, $supplier->name, $notes);
        }

        return $supplier->refresh();
    }

    private function returnItem(
        User $reviewer,
        SupplierPortfolioItem $item,
        ContentStatus $next,
        ContentReviewAction $action,
        string $notes,
        SupplierContentType $type,
    ): SupplierPortfolioItem {
        $this->assertReviewer($reviewer);
        if (! in_array($item->status, [ContentStatus::Submitted, ContentStatus::UnderReview], true)) {
            throw new ContentWorkflowException('لا يمكن مراجعة العمل في هذه الحالة.');
        }
        $from = $item->status;
        $item->forceFill([
            'status' => $next,
            'review_notes' => $notes,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ])->save();
        $this->logger->record($item, $reviewer, $action, $from, $next, $notes);
        if ($next === ContentStatus::Rejected) {
            $this->notifier->supplierContentRejected($item->supplier, $type, $item->title, $notes);
        } else {
            $this->notifier->supplierContentChangesRequested($item->supplier, $type, $item->title, $notes);
        }

        return $item->refresh();
    }

    private function returnProduct(
        User $reviewer,
        SupplierProduct $product,
        ContentStatus $next,
        ContentReviewAction $action,
        string $notes,
    ): SupplierProduct {
        $this->assertReviewer($reviewer);
        if (! in_array($product->status, [ContentStatus::Submitted, ContentStatus::UnderReview], true)) {
            throw new ContentWorkflowException('لا يمكن مراجعة المنتج في هذه الحالة.');
        }
        $from = $product->status;
        $product->forceFill([
            'status' => $next,
            'review_notes' => $notes,
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
        ])->save();
        $this->logger->record($product, $reviewer, $action, $from, $next, $notes);
        if ($next === ContentStatus::Rejected) {
            $this->notifier->supplierContentRejected($product->supplier, SupplierContentType::Product, $product->name, $notes);
        } else {
            $this->notifier->supplierContentChangesRequested($product->supplier, SupplierContentType::Product, $product->name, $notes);
        }

        return $product->refresh();
    }

    private function assertReviewer(User $user): void
    {
        if (! $user->canReviewContent()) {
            throw new ContentWorkflowException('Forbidden.', 403);
        }
    }

    private function assertSupplierOwns(User $user, int $supplierId): void
    {
        if ($user->role !== UserRole::Supplier || (int) $this->supplierFor($user)->id !== $supplierId) {
            throw new ContentWorkflowException('Forbidden.', 403);
        }
    }

    private function assertNotBlocked(Supplier $supplier): void
    {
        if ($supplier->status === SupplierStatus::Blocked) {
            throw new ContentWorkflowException('تم حظر حساب المورد.', 403);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function withoutLockedFields(Supplier $supplier, array $payload): array
    {
        $locked = array_map('strval', $supplier->locked_fields ?? []);
        if ($locked === []) {
            return $payload;
        }

        foreach ($locked as $field) {
            unset($payload[$field]);
        }

        return $payload;
    }

    private function assertReviewable(ContentStatus $status): void
    {
        if (! $status->reviewerCanModerate()) {
            throw new ContentWorkflowException('لا يمكن النشر في هذه الحالة.');
        }
    }
}
