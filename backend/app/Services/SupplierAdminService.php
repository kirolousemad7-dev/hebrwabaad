<?php

namespace App\Services;

use App\Enums\ContentReviewAction;
use App\Enums\ContentStatus;
use App\Enums\SupplierOnboardingStatus;
use App\Enums\SupplierStatus;
use App\Enums\SupplierVerificationStatus;
use App\Enums\UserRole;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class SupplierAdminService
{
    public function __construct(
        private readonly ContentReviewLogger $logger,
        private readonly SupplierContentService $content,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Supplier>
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Supplier::query()
            ->withCount([
                'portfolioItems',
                'products',
                'publicPortfolioItems',
                'publicProducts',
            ])
            ->with('user')
            ->when(
                is_string($filters['q'] ?? null) && trim((string) $filters['q']) !== '',
                function ($query) use ($filters): void {
                    $term = '%'.trim((string) $filters['q']).'%';
                    $query->where(function ($inner) use ($term): void {
                        $inner->where('name', 'like', $term)
                            ->orWhere('location', 'like', $term)
                            ->orWhere('slug', 'like', $term);
                    });
                },
            )
            ->when(isset($filters['is_active']), fn ($query) => $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN)))
            ->when(isset($filters['is_published']), fn ($query) => $query->where('is_published', filter_var($filters['is_published'], FILTER_VALIDATE_BOOLEAN)))
            ->when(isset($filters['is_featured']), fn ($query) => $query->where('is_featured', filter_var($filters['is_featured'], FILTER_VALIDATE_BOOLEAN)))
            ->when(is_string($filters['category'] ?? null), fn ($query) => $query->where('category', $filters['category']))
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
            $userId = $payload['user_id'] ?? null;

            if (isset($payload['account_email'])) {
                $user = User::query()->create([
                    'name' => $payload['account_name'] ?? $payload['name'],
                    'email' => $payload['account_email'],
                    'password' => $payload['account_password'],
                    'role' => UserRole::Supplier,
                    'is_active' => true,
                ]);
                $userId = $user->id;
            }

            $supplier = Supplier::query()->create([
                'user_id' => $userId,
                'name' => $payload['name'],
                'legal_name' => $payload['legal_name'] ?? $payload['name'],
                'display_name' => $payload['display_name'] ?? $payload['name'],
                'slug' => $payload['slug'] ?? Supplier::uniqueSlug($payload['name']),
                'logo' => $payload['logo'] ?? '/brand/logo.png',
                'cover_image' => $payload['cover_image'] ?? null,
                'short_description' => $payload['short_description'] ?? '',
                'description' => $payload['description'] ?? null,
                'specialties' => $payload['specialties'] ?? [],
                'services' => $payload['services'] ?? [],
                'location' => $payload['location'] ?? '',
                'country' => $payload['country'] ?? null,
                'city' => $payload['city'] ?? null,
                'address' => $payload['address'] ?? null,
                'email' => $payload['email'] ?? null,
                'phone' => $payload['phone'] ?? null,
                'whatsapp' => $payload['whatsapp'] ?? null,
                'website' => $payload['website'] ?? null,
                'category' => $payload['category'] ?? null,
                'brand_colors' => $payload['brand_colors'] ?? null,
                'brand_description' => $payload['brand_description'] ?? null,
                'years_experience' => $payload['years_experience'] ?? null,
                'min_order_info' => $payload['min_order_info'] ?? null,
                'sort_order' => (int) ($payload['sort_order'] ?? 0),
                'is_active' => (bool) ($payload['is_active'] ?? true),
                'is_featured' => (bool) ($payload['is_featured'] ?? false),
                'is_published' => false,
                'show_public_contact' => (bool) ($payload['show_public_contact'] ?? false),
                'status' => $payload['status'] ?? SupplierStatus::Pending->value,
                'verification_status' => $payload['verification_status'] ?? SupplierVerificationStatus::Unverified->value,
                'onboarding_status' => $payload['onboarding_status'] ?? SupplierOnboardingStatus::Draft->value,
                'notes' => $payload['notes'] ?? null,
                'internal_notes' => $payload['internal_notes'] ?? null,
                'company_id' => $payload['company_id'] ?? null,
                'profile_status' => ContentStatus::Draft,
                'created_by' => $actor->id,
                'updated_by' => $actor->id,
            ]);

            $supplier->forceFill([
                'supplier_code' => $payload['supplier_code'] ?? 'SUP-'.str_pad((string) $supplier->id, 5, '0', STR_PAD_LEFT),
            ])->save();

            $this->logger->record($supplier, $actor, ContentReviewAction::Created, null, ContentStatus::Draft);

            return $supplier->refresh();
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(User $actor, Supplier $supplier, array $payload): Supplier
    {
        unset($payload['profile_status'], $payload['user_id']);

        if (array_key_exists('slug', $payload) && is_string($payload['slug']) && $payload['slug'] !== $supplier->slug) {
            $payload['slug'] = Supplier::uniqueSlug($payload['slug'], $supplier->id);
        }

        $supplier->fill($payload);

        if (array_key_exists('is_published', $payload) && $payload['is_published']) {
            $supplier->profile_status = ContentStatus::Published;
            $supplier->published_at = now();
        }

        if (array_key_exists('is_published', $payload) && $payload['is_published'] === false) {
            $supplier->is_published = false;
        }

        $supplier->save();

        return $supplier->refresh();
    }

    public function setActive(Supplier $supplier, bool $active): Supplier
    {
        $supplier->forceFill(['is_active' => $active])->save();

        return $supplier->refresh();
    }

    public function unpublish(User $actor, Supplier $supplier): Supplier
    {
        $from = $supplier->profile_status;
        $supplier->forceFill(['is_published' => false])->save();
        $this->logger->record($supplier, $actor, ContentReviewAction::Unpublished, $from, $from);

        return $supplier->refresh();
    }

    public function delete(Supplier $supplier): void
    {
        $supplier->delete();
    }

    public function approveAndPublish(User $actor, Supplier $supplier): Supplier
    {
        return $this->content->approveProfile($actor, $supplier);
    }
}
