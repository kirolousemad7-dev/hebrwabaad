<?php

namespace App\Services\Media;

use App\Enums\MediaVisibility;
use App\Enums\UserRole;
use App\Models\CommercialQuotation;
use App\Models\CrmCompany;
use App\Models\Invoice;
use App\Models\Media;
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
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MediaService
{
    public const MAX_KILOBYTES = 40960;

    /**
     * @var list<string>
     */
    public const ALLOWED_EXTENSIONS = [
        'pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif',
        'doc', 'docx', 'xls', 'xlsx', 'csv',
        'mp4', 'webm',
    ];

    /**
     * @var list<string>
     */
    public const ALLOWED_MIMES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv',
        'text/plain',
        'video/mp4',
        'video/webm',
    ];

    public function __construct(private readonly MediaEntityRegistry $registry) {}

    /**
     * @return list<string>
     */
    public function eagerLoad(): array
    {
        return ['uploader', 'owner'];
    }

    public function disk(): string
    {
        return (string) config('filesystems.default', 'local');
    }

    /**
     * PUBLIC catalog media must live on the publicly linked disk so the SPA can
     * render absolute /storage URLs. All other visibility levels stay private.
     */
    public function diskForVisibility(MediaVisibility $visibility): string
    {
        return $visibility === MediaVisibility::Public ? 'public' : $this->disk();
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Media>
     */
    public function paginateFor(User $actor, array $filters): LengthAwarePaginator
    {
        $query = Media::query()->with($this->eagerLoad());
        $this->scopeVisibleTo($query, $actor);

        if (isset($filters['entity_type'], $filters['entity_id'])
            && $filters['entity_type'] !== ''
            && $filters['entity_id'] !== '') {
            $owner = $this->registry->resolve((string) $filters['entity_type'], (int) $filters['entity_id']);
            $this->assertCanAttach($actor, $owner, viewOnly: true);
            $query->where('owner_type', $this->registry->morphAlias((string) $filters['entity_type']))
                ->where('owner_id', $owner->getKey());
        }

        if (isset($filters['visibility']) && $filters['visibility'] !== '') {
            $query->where('visibility', (string) $filters['visibility']);
        }

        return $query->orderByDesc('is_primary')->orderBy('sort_order')->orderByDesc('id')->paginate($this->perPage($filters));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function store(User $actor, UploadedFile $upload, array $attributes): Media
    {
        $entityType = (string) ($attributes['entity_type'] ?? '');
        $entityId = (int) ($attributes['entity_id'] ?? 0);
        $owner = $this->registry->resolve($entityType, $entityId);
        $this->assertCanAttach($actor, $owner);

        $visibility = $this->resolveVisibility($attributes['visibility'] ?? null, $entityType);
        $this->assertUpload($upload);

        $disk = $this->diskForVisibility($visibility);
        $extension = strtolower((string) ($upload->getClientOriginalExtension() ?: $upload->guessExtension() ?: 'bin'));
        $storedName = Str::uuid()->toString().'.'.$extension;
        $directory = 'media/'.$entityType;
        $path = $upload->storeAs($directory, $storedName, $disk);

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'file' => ['The file could not be stored.'],
            ]);
        }

        $absolute = Storage::disk($disk)->path($path);
        $checksum = is_file($absolute) ? hash_file('sha256', $absolute) : null;

        $collection = is_string($attributes['collection'] ?? null) && $attributes['collection'] !== ''
            ? (string) $attributes['collection']
            : 'default';

        $nextOrder = (int) Media::query()
            ->where('owner_type', $this->registry->morphAlias($entityType))
            ->where('owner_id', $owner->getKey())
            ->where('collection', $collection)
            ->max('sort_order');

        $makePrimary = (bool) ($attributes['is_primary'] ?? false)
            || ! Media::query()
                ->where('owner_type', $this->registry->morphAlias($entityType))
                ->where('owner_id', $owner->getKey())
                ->where('collection', $collection)
                ->where('is_primary', true)
                ->exists();

        $media = DB::transaction(function () use (
            $actor,
            $attributes,
            $checksum,
            $collection,
            $disk,
            $entityType,
            $extension,
            $makePrimary,
            $nextOrder,
            $owner,
            $path,
            $upload,
            $visibility,
        ): Media {
            if ($makePrimary) {
                Media::query()
                    ->where('owner_type', $this->registry->morphAlias($entityType))
                    ->where('owner_id', $owner->getKey())
                    ->where('collection', $collection)
                    ->update(['is_primary' => false]);
            }

            return Media::query()->create([
                'disk' => $disk,
                'path' => $path,
                'original_name' => $this->safeOriginalName($upload),
                'mime_type' => $upload->getMimeType() ?: 'application/octet-stream',
                'extension' => $extension,
                'size' => (int) ($upload->getSize() ?: 0),
                'checksum' => $checksum,
                'visibility' => $visibility,
                'uploaded_by' => $actor->id,
                'owner_type' => $this->registry->morphAlias($entityType),
                'owner_id' => $owner->getKey(),
                'metadata' => $this->buildMetadata($upload, $attributes['metadata'] ?? null),
                'collection' => $collection,
                'sort_order' => $nextOrder + 1,
                'is_primary' => $makePrimary,
            ]);
        });

        return $media->load($this->eagerLoad());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function replace(User $actor, Media $media, UploadedFile $upload, array $attributes = []): Media
    {
        $this->assertUpload($upload);

        $extension = strtolower((string) ($upload->getClientOriginalExtension() ?: $upload->guessExtension() ?: 'bin'));
        $storedName = Str::uuid()->toString().'.'.$extension;
        $entityType = $this->registry->entityTypeFor($media->owner) ?? 'misc';
        $directory = 'media/'.$entityType;

        $visibility = $media->visibilityEnum() ?? MediaVisibility::Internal;
        if (isset($attributes['visibility']) && $attributes['visibility'] !== null && $attributes['visibility'] !== '') {
            $visibility = MediaVisibility::from((string) $attributes['visibility']);
        }

        $disk = $this->diskForVisibility($visibility);
        $path = $upload->storeAs($directory, $storedName, $disk);

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'file' => ['The file could not be stored.'],
            ]);
        }

        $oldDisk = $media->disk;
        $oldPath = $media->path;

        $absolute = Storage::disk($disk)->path($path);
        $checksum = is_file($absolute) ? hash_file('sha256', $absolute) : null;

        $media->fill([
            'disk' => $disk,
            'path' => $path,
            'original_name' => $this->safeOriginalName($upload),
            'mime_type' => $upload->getMimeType() ?: 'application/octet-stream',
            'extension' => $extension,
            'size' => (int) ($upload->getSize() ?: 0),
            'checksum' => $checksum,
            'visibility' => $visibility,
            'metadata' => array_merge($media->metadata ?? [], $this->buildMetadata($upload, $attributes['metadata'] ?? null)),
        ]);

        $media->save();

        if (is_string($oldPath) && $oldPath !== '' && $oldPath !== $path) {
            Storage::disk($oldDisk)->delete($oldPath);
        }

        return $media->fresh($this->eagerLoad()) ?? $media->load($this->eagerLoad());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateMeta(Media $media, array $attributes): Media
    {
        $visibilityChanged = false;

        if (isset($attributes['visibility']) && $attributes['visibility'] !== null && $attributes['visibility'] !== '') {
            $nextVisibility = MediaVisibility::from((string) $attributes['visibility']);
            $visibilityChanged = $media->visibilityEnum() !== $nextVisibility;
            $media->visibility = $nextVisibility;
        }

        if (array_key_exists('metadata', $attributes) && is_array($attributes['metadata'])) {
            $media->metadata = array_merge($media->metadata ?? [], $attributes['metadata']);
        }

        if (isset($attributes['collection']) && is_string($attributes['collection']) && $attributes['collection'] !== '') {
            $media->collection = $attributes['collection'];
        }

        if (array_key_exists('sort_order', $attributes) && $attributes['sort_order'] !== null) {
            $media->sort_order = (int) $attributes['sort_order'];
        }

        if ($visibilityChanged && $media->visibilityEnum() !== null) {
            $this->relocateForVisibility($media, $media->visibilityEnum());
        }

        $media->save();

        if (array_key_exists('is_primary', $attributes) && (bool) $attributes['is_primary'] === true) {
            return $this->setPrimary($media);
        }

        return $media->fresh($this->eagerLoad()) ?? $media->load($this->eagerLoad());
    }

    /**
     * Keep disk aligned with visibility so demoting PUBLIC never leaves a
     * readable object under the public storage symlink.
     */
    private function relocateForVisibility(Media $media, MediaVisibility $visibility): void
    {
        $targetDisk = $this->diskForVisibility($visibility);
        $currentDisk = (string) ($media->disk ?: $this->disk());
        $path = (string) $media->path;

        if ($targetDisk === $currentDisk || $path === '') {
            return;
        }

        if (! Storage::disk($currentDisk)->exists($path)) {
            $media->disk = $targetDisk;

            return;
        }

        Storage::disk($targetDisk)->put($path, Storage::disk($currentDisk)->get($path));
        Storage::disk($currentDisk)->delete($path);
        $media->disk = $targetDisk;
    }

    public function setPrimary(Media $media): Media
    {
        return DB::transaction(function () use ($media): Media {
            Media::query()
                ->where('owner_type', $media->owner_type)
                ->where('owner_id', $media->owner_id)
                ->where('collection', $media->collection ?: 'default')
                ->whereKeyNot($media->id)
                ->update(['is_primary' => false]);

            $media->is_primary = true;
            $media->save();

            return $media->fresh($this->eagerLoad()) ?? $media->load($this->eagerLoad());
        });
    }

    /**
     * @param  list<int>  $orderedIds
     * @return list<Media>
     */
    public function reorder(User $actor, string $entityType, int $entityId, array $orderedIds, string $collection = 'default'): array
    {
        $owner = $this->registry->resolve($entityType, $entityId);
        $this->assertCanAttach($actor, $owner);

        $morph = $this->registry->morphAlias($entityType);
        $ids = array_values(array_unique(array_map('intval', $orderedIds)));

        $existing = Media::query()
            ->where('owner_type', $morph)
            ->where('owner_id', $owner->getKey())
            ->where('collection', $collection)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->all();

        if (count($existing) !== count($ids)) {
            throw ValidationException::withMessages([
                'ordered_ids' => ['One or more media items do not belong to this entity.'],
            ]);
        }

        DB::transaction(function () use ($ids, $morph, $owner, $collection): void {
            foreach ($ids as $index => $id) {
                Media::query()
                    ->whereKey($id)
                    ->where('owner_type', $morph)
                    ->where('owner_id', $owner->getKey())
                    ->where('collection', $collection)
                    ->update(['sort_order' => $index + 1]);
            }
        });

        return Media::query()
            ->with($this->eagerLoad())
            ->where('owner_type', $morph)
            ->where('owner_id', $owner->getKey())
            ->where('collection', $collection)
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->all();
    }

    public function duplicate(User $actor, Media $media): Media
    {
        $this->assertStored($media);

        $extension = $media->extension ?: pathinfo($media->path, PATHINFO_EXTENSION) ?: 'bin';
        $storedName = Str::uuid()->toString().'.'.$extension;
        $entityType = $this->registry->entityTypeFor($media->owner) ?? 'misc';
        $directory = 'media/'.$entityType;
        $newPath = $directory.'/'.$storedName;

        Storage::disk($media->disk)->copy($media->path, $newPath);

        $copy = Media::query()->create([
            'disk' => $media->disk,
            'path' => $newPath,
            'original_name' => $media->original_name,
            'mime_type' => $media->mime_type,
            'extension' => $media->extension,
            'size' => $media->size,
            'checksum' => $media->checksum,
            'visibility' => $media->visibility,
            'uploaded_by' => $actor->id,
            'owner_type' => $media->owner_type,
            'owner_id' => $media->owner_id,
            'metadata' => array_merge($media->metadata ?? [], [
                'duplicated_from' => $media->id,
            ]),
            'collection' => $media->collection ?: 'default',
            'sort_order' => ((int) Media::query()
                ->where('owner_type', $media->owner_type)
                ->where('owner_id', $media->owner_id)
                ->where('collection', $media->collection ?: 'default')
                ->max('sort_order')) + 1,
            'is_primary' => false,
        ]);

        return $copy->load($this->eagerLoad());
    }

    public function delete(Media $media): void
    {
        $disk = $media->disk;
        $path = $media->path;
        $media->delete();

        if (is_string($path) && $path !== '') {
            Storage::disk($disk)->delete($path);
        }
    }

    public function download(Media $media): StreamedResponse
    {
        $this->assertStored($media);

        return Storage::disk($media->disk)->download($media->path, $media->original_name);
    }

    public function preview(Media $media): StreamedResponse
    {
        if (! $media->isPreviewable()) {
            abort(404, __('messages.not_found'));
        }

        $this->assertStored($media);

        $filename = str_replace(['"', "\r", "\n", '\\'], '', $media->original_name);

        return Storage::disk($media->disk)->response(
            $media->path,
            $filename,
            ['Content-Disposition' => 'inline; filename="'.$filename.'"'],
        );
    }

    /**
     * Stream PUBLIC media without authentication (legacy private-disk rows and
     * any PUBLIC file that is not yet reachable via /storage).
     */
    public function publicFile(Media $media): StreamedResponse
    {
        if ($media->visibilityEnum() !== MediaVisibility::Public) {
            abort(404, __('messages.not_found'));
        }

        $this->assertStored($media);

        $filename = str_replace(['"', "\r", "\n", '\\'], '', $media->original_name);
        $headers = [
            'Content-Type' => (string) ($media->mime_type ?: 'application/octet-stream'),
            'Cache-Control' => 'public, max-age=86400',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ];

        return Storage::disk($media->disk)->response($media->path, $filename, $headers);
    }

    public function assertCanAttach(User $actor, Model $owner, bool $viewOnly = false): void
    {
        if (! $actor->is_active || ! $actor->role instanceof UserRole) {
            throw ValidationException::withMessages([
                'entity_id' => ['You are not allowed to manage media for this entity.'],
            ]);
        }

        if ($actor->role === UserRole::Owner || $actor->role === UserRole::AdminManager) {
            return;
        }

        if ($actor->role->isStaff()) {
            if ($this->staffCanAccessOwner($actor, $owner)) {
                return;
            }
        }

        if ($actor->role === UserRole::Customer && $this->customerCanAccessOwner($actor, $owner)) {
            return;
        }

        if ($actor->role === UserRole::Supplier && $this->supplierCanAccessOwner($actor, $owner)) {
            return;
        }

        throw ValidationException::withMessages([
            'entity_id' => [$viewOnly
                ? 'You are not allowed to view media for this entity.'
                : 'You are not allowed to manage media for this entity.'],
        ]);
    }

    private function staffCanAccessOwner(User $actor, Model $owner): bool
    {
        if ($owner instanceof Task) {
            if ($actor->role === UserRole::AccountManager) {
                return $owner->project?->account_manager_id === $actor->id
                    || (int) $owner->created_by === (int) $actor->id;
            }

            return (int) $owner->assigned_to === (int) $actor->id;
        }

        if ($owner instanceof Project) {
            if ($actor->role === UserRole::AccountManager) {
                return (int) $owner->account_manager_id === (int) $actor->id;
            }

            return $owner->tasks()->where('assigned_to', $actor->id)->exists();
        }

        if ($owner instanceof CommercialQuotation) {
            return $actor->role === UserRole::AccountManager
                || (int) $owner->created_by === (int) $actor->id;
        }

        if ($owner instanceof Meeting) {
            return (int) $owner->created_by === (int) $actor->id
                || $owner->participants()->where('users.id', $actor->id)->exists();
        }

        if ($owner instanceof CrmCompany
            || $owner instanceof Supplier
            || $owner instanceof SupplierProduct
            || $owner instanceof SupplierPortfolioItem
            || $owner instanceof Service
            || $owner instanceof Package
            || $owner instanceof PrintingProduct
            || $owner instanceof PortfolioItem
            || $owner instanceof Payment
            || $owner instanceof Invoice
            || $owner instanceof User) {
            return $actor->role === UserRole::AccountManager
                || $actor->role->canManageCatalog()
                || $actor->role->canManageOrders();
        }

        return false;
    }

    private function customerCanAccessOwner(User $actor, Model $owner): bool
    {
        if ($owner instanceof User) {
            return (int) $owner->id === (int) $actor->id && $owner->role === UserRole::Customer;
        }

        if ($owner instanceof Project) {
            return (int) $owner->customer_id === (int) $actor->id;
        }

        if ($owner instanceof CommercialQuotation) {
            return (int) $owner->customer_id === (int) $actor->id;
        }

        if ($owner instanceof Payment) {
            return (int) $owner->customer_id === (int) $actor->id;
        }

        if ($owner instanceof Meeting) {
            return (int) ($owner->customer_id ?? 0) === (int) $actor->id && $owner->include_customer;
        }

        return false;
    }

    private function supplierCanAccessOwner(User $actor, Model $owner): bool
    {
        $supplier = Supplier::query()->where('user_id', $actor->id)->first();

        if ($supplier === null) {
            return false;
        }

        if ($owner instanceof Supplier) {
            return (int) $owner->id === (int) $supplier->id;
        }

        if ($owner instanceof SupplierProduct) {
            return (int) $owner->supplier_id === (int) $supplier->id;
        }

        if ($owner instanceof SupplierPortfolioItem) {
            return (int) $owner->supplier_id === (int) $supplier->id;
        }

        if ($owner instanceof Task) {
            return (int) ($owner->supplier_id ?? 0) === (int) $supplier->id;
        }

        if ($owner instanceof Meeting) {
            return (int) ($owner->supplier_id ?? 0) === (int) $supplier->id;
        }

        return false;
    }

    /**
     * @param  Builder<Media>  $query
     */
    private function scopeVisibleTo(Builder $query, User $user): void
    {
        if ($user->role === UserRole::Owner || $user->role === UserRole::AdminManager) {
            return;
        }

        $query->where(function (Builder $outer) use ($user): void {
            $outer->where('uploaded_by', $user->id)
                ->orWhere('visibility', MediaVisibility::Public->value);

            if ($user->role?->isStaff()) {
                $outer->orWhereIn('visibility', [
                    MediaVisibility::Internal->value,
                    MediaVisibility::Customer->value,
                    MediaVisibility::Supplier->value,
                    MediaVisibility::Private->value,
                ]);
            }

            if ($user->role === UserRole::Customer) {
                $outer->orWhere(function (Builder $inner) use ($user): void {
                    $inner->where('visibility', MediaVisibility::Customer->value)
                        ->where(function (Builder $ownerQuery) use ($user): void {
                            $ownerQuery->where(function (Builder $q) use ($user): void {
                                $q->where('owner_type', 'customer')->where('owner_id', $user->id);
                            })->orWhere(function (Builder $q) use ($user): void {
                                $q->where('owner_type', 'project')
                                    ->whereIn('owner_id', Project::query()->where('customer_id', $user->id)->select('id'));
                            })->orWhere(function (Builder $q) use ($user): void {
                                $q->where('owner_type', 'quotation')
                                    ->whereIn('owner_id', CommercialQuotation::query()->where('customer_id', $user->id)->select('id'));
                            })->orWhere(function (Builder $q) use ($user): void {
                                $q->where('owner_type', 'invoice')
                                    ->whereIn('owner_id', Invoice::query()->where('customer_id', $user->id)->select('id'));
                            })->orWhere(function (Builder $q) use ($user): void {
                                $q->where('owner_type', 'payment')
                                    ->whereIn('owner_id', Payment::query()->where('customer_id', $user->id)->select('id'));
                            });
                        });
                });
            }

            if ($user->role === UserRole::Supplier) {
                $supplierId = Supplier::query()->where('user_id', $user->id)->value('id');
                if ($supplierId !== null) {
                    $outer->orWhere(function (Builder $inner) use ($supplierId): void {
                        $inner->where('visibility', MediaVisibility::Supplier->value)
                            ->where(function (Builder $ownerQuery) use ($supplierId): void {
                                $ownerQuery->where(function (Builder $q) use ($supplierId): void {
                                    $q->where('owner_type', 'supplier')->where('owner_id', $supplierId);
                                })->orWhere(function (Builder $q) use ($supplierId): void {
                                    $q->where('owner_type', 'product')
                                        ->whereIn('owner_id', SupplierProduct::query()->where('supplier_id', $supplierId)->select('id'));
                                })->orWhere(function (Builder $q) use ($supplierId): void {
                                    $q->where('owner_type', 'supplier_portfolio_item')
                                        ->whereIn('owner_id', SupplierPortfolioItem::query()->where('supplier_id', $supplierId)->select('id'));
                                })->orWhere(function (Builder $q) use ($supplierId): void {
                                    $q->where('owner_type', 'task')
                                        ->whereIn('owner_id', Task::query()->where('supplier_id', $supplierId)->select('id'));
                                });
                            });
                    });
                }
            }
        });
    }

    private function resolveVisibility(mixed $raw, string $entityType): MediaVisibility
    {
        if (is_string($raw) && $raw !== '') {
            return MediaVisibility::from($raw);
        }

        return match ($entityType) {
            'customer' => MediaVisibility::Customer,
            'supplier', 'product', 'supplier_portfolio_item' => MediaVisibility::Supplier,
            'portfolio', 'package', 'service', 'printing_product', 'marketing_section', 'marketing_content' => MediaVisibility::Public,
            default => MediaVisibility::Internal,
        };
    }

    private function assertUpload(UploadedFile $upload): void
    {
        $extension = strtolower((string) ($upload->getClientOriginalExtension() ?: $upload->guessExtension() ?: ''));
        $mime = (string) ($upload->getMimeType() ?: '');
        $sizeKb = (int) ceil(((int) $upload->getSize()) / 1024);

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'file' => ['File extension is not allowed.'],
            ]);
        }

        if ($mime !== '' && ! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages([
                'file' => ['File MIME type is not allowed.'],
            ]);
        }

        if ($sizeKb > self::MAX_KILOBYTES) {
            throw ValidationException::withMessages([
                'file' => ['File exceeds the maximum allowed size.'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>|null  $extra
     * @return array<string, mixed>
     */
    private function buildMetadata(UploadedFile $upload, mixed $extra): array
    {
        $meta = is_array($extra) ? $extra : [];
        $mime = (string) ($upload->getMimeType() ?: '');

        if (str_starts_with($mime, 'image/')) {
            $dimensions = @getimagesize($upload->getRealPath() ?: '');
            if (is_array($dimensions)) {
                $meta['width'] = (int) $dimensions[0];
                $meta['height'] = (int) $dimensions[1];
            }
        }

        if (str_starts_with($mime, 'video/')) {
            $meta['kind'] = 'video';
            $meta['client_name'] = $upload->getClientOriginalName();
        }

        if ($mime === 'application/pdf') {
            $meta['kind'] = 'pdf';
        }

        return $meta;
    }

    private function safeOriginalName(UploadedFile $file): string
    {
        $name = basename($file->getClientOriginalName());

        return Str::limit($name === '' ? 'file' : $name, 255, '');
    }

    private function assertStored(Media $media): void
    {
        if (! Storage::disk($media->disk)->exists($media->path)) {
            abort(404, __('messages.not_found'));
        }
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        return max(1, min((int) ($filters['per_page'] ?? 15), 50));
    }
}
