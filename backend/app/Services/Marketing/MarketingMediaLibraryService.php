<?php

namespace App\Services\Marketing;

use App\Enums\MediaVisibility;
use App\Models\MarketingContent;
use App\Models\MarketingSection;
use App\Models\Media;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Marketing media library built on the existing Media table (MediaAsset role).
 *
 * Safe replace strategy:
 * 1) Upload creates a NEW Media row
 * 2) Caller updates MarketingContent.media_id to the new id
 * 3) Previous Media remains until unused and explicitly deleted
 */
class MarketingMediaLibraryService
{
    public const COLLECTION = 'marketing_library';

    public const MAX_KILOBYTES = 8192;

    /**
     * @var list<string>
     */
    public const ALLOWED_EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    /**
     * @var list<string>
     */
    public const ALLOWED_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/gif',
    ];

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Media>
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = Media::query()
            ->with(['uploader'])
            ->where('collection', self::COLLECTION)
            ->orderByDesc('id');

        if (isset($filters['is_active'])) {
            $active = filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($active === true) {
                $query->where(function ($q): void {
                    $q->whereNull('metadata->is_active')
                        ->orWhere('metadata->is_active', true)
                        ->orWhere('metadata->is_active', 'true')
                        ->orWhere('metadata->is_active', 1);
                });
            } elseif ($active === false) {
                $query->where(function ($q): void {
                    $q->where('metadata->is_active', false)
                        ->orWhere('metadata->is_active', 'false')
                        ->orWhere('metadata->is_active', 0);
                });
            }
        }

        $perPage = max(1, min((int) ($filters['per_page'] ?? 24), 50));

        return $query->paginate($perPage);
    }

    public function find(int $id): Media
    {
        $media = Media::query()->with(['uploader'])->findOrFail($id);
        $this->assertLibraryMedia($media);

        return $media;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upload(User $actor, UploadedFile $upload, array $attributes = []): Media
    {
        $this->assertImageUpload($upload);

        $disk = 'public';
        $extension = strtolower((string) ($upload->getClientOriginalExtension() ?: $upload->guessExtension() ?: 'bin'));
        $storedName = Str::uuid()->toString().'.'.$extension;
        $path = $upload->storeAs('media/marketing', $storedName, $disk);

        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages([
                'file' => ['The file could not be stored.'],
            ]);
        }

        $absolute = Storage::disk($disk)->path($path);
        $checksum = is_file($absolute) ? hash_file('sha256', $absolute) : null;

        $owner = null;
        if (isset($attributes['section_key']) && is_string($attributes['section_key']) && $attributes['section_key'] !== '') {
            $owner = MarketingSection::query()->where('key', $attributes['section_key'])->first();
        }

        $metadata = $this->buildMetadata($upload, $attributes);

        return Media::query()->create([
            'disk' => $disk,
            'path' => $path,
            'original_name' => $this->safeOriginalName($upload),
            'mime_type' => $upload->getMimeType() ?: 'application/octet-stream',
            'extension' => $extension,
            'size' => (int) ($upload->getSize() ?: 0),
            'checksum' => $checksum,
            'visibility' => MediaVisibility::Public,
            'uploaded_by' => $actor->id,
            'owner_type' => $owner ? 'marketing_section' : null,
            'owner_id' => $owner?->id,
            'metadata' => $metadata,
            'collection' => self::COLLECTION,
            'sort_order' => 0,
            'is_primary' => false,
        ])->load(['uploader']);
    }

    /**
     * Register a known local public-path asset without moving/deleting the SPA file.
     * Used by seeders so Phase 3 can bind Media rows to frontend fallbacks.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function registerLocalPublicAsset(User $actor, string $publicPath, array $attributes = []): Media
    {
        $publicPath = '/'.ltrim($publicPath, '/');

        $existing = Media::query()
            ->where('collection', self::COLLECTION)
            ->where('metadata->local_public_path', $publicPath)
            ->first();

        if ($existing !== null) {
            return $existing->load(['uploader']);
        }

        $filename = basename($publicPath);

        return Media::query()->create([
            'disk' => 'public',
            'path' => 'marketing-registry/'.ltrim($publicPath, '/'),
            'original_name' => $filename,
            'mime_type' => $this->guessMimeFromPath($publicPath),
            'extension' => strtolower(pathinfo($filename, PATHINFO_EXTENSION) ?: ''),
            'size' => 0,
            'checksum' => null,
            'visibility' => MediaVisibility::Public,
            'uploaded_by' => $actor->id,
            'owner_type' => null,
            'owner_id' => null,
            'metadata' => array_merge([
                'title' => $attributes['title'] ?? $filename,
                'alt_text' => $attributes['alt_text'] ?? '',
                'source_key' => $attributes['source_key'] ?? null,
                'local_public_path' => $publicPath,
                'is_active' => true,
                'is_registry_only' => true,
                'used_by' => $attributes['used_by'] ?? [],
            ], is_array($attributes['metadata'] ?? null) ? $attributes['metadata'] : []),
            'collection' => self::COLLECTION,
            'sort_order' => 0,
            'is_primary' => false,
        ])->load(['uploader']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateMeta(Media $media, array $attributes): Media
    {
        $this->assertLibraryMedia($media);

        $meta = $media->metadata ?? [];

        if (array_key_exists('alt_text', $attributes)) {
            $meta['alt_text'] = is_string($attributes['alt_text']) ? $attributes['alt_text'] : '';
        }
        if (array_key_exists('title', $attributes)) {
            $meta['title'] = is_string($attributes['title']) ? $attributes['title'] : '';
        }
        if (array_key_exists('is_active', $attributes)) {
            $meta['is_active'] = (bool) $attributes['is_active'];
        }
        if (array_key_exists('metadata', $attributes) && is_array($attributes['metadata'])) {
            $meta = array_merge($meta, $attributes['metadata']);
        }

        $media->metadata = $meta;
        $media->save();

        return $media->fresh(['uploader']) ?? $media->load(['uploader']);
    }

    /**
     * Upload a replacement file as a NEW Media row. Does not delete the old asset.
     *
     * @param  array<string, mixed>  $attributes
     * @return array{new: Media, previous: Media}
     */
    public function uploadReplacement(User $actor, Media $previous, UploadedFile $upload, array $attributes = []): array
    {
        $this->assertLibraryMedia($previous);
        $new = $this->upload($actor, $upload, array_merge($attributes, [
            'replaced_from' => $previous->id,
        ]));

        return [
            'new' => $new,
            'previous' => $previous,
        ];
    }

    /**
     * Delete only when the media is not referenced by marketing content
     * and is not a registry-only placeholder for a local /public asset.
     */
    public function deleteIfUnused(Media $media): void
    {
        $this->assertLibraryMedia($media);

        $isRegistryOnly = (bool) (($media->metadata ?? [])['is_registry_only'] ?? false);

        if ($isRegistryOnly) {
            throw ValidationException::withMessages([
                'media' => ['Cannot delete registry-only marketing media. Replace it instead of deleting.'],
            ]);
        }

        if ($this->usageCount($media) > 0) {
            throw ValidationException::withMessages([
                'media' => ['Cannot delete media that is still referenced by marketing content.'],
            ]);
        }

        $disk = $media->disk;
        $path = $media->path;

        $media->delete();

        if (is_string($path) && $path !== '') {
            Storage::disk($disk)->delete($path);
        }
    }

    public function usageCount(Media $media): int
    {
        return MarketingContent::query()->where('media_id', $media->id)->count();
    }

    /**
     * @return list<array{section_key: string, content_key: string, content_id: int}>
     */
    public function usages(Media $media): array
    {
        return MarketingContent::query()
            ->with('section:id,key')
            ->where('media_id', $media->id)
            ->get()
            ->map(static function (MarketingContent $content): array {
                return [
                    'section_key' => (string) ($content->section?->key ?? ''),
                    'content_key' => (string) $content->content_key,
                    'content_id' => (int) $content->id,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Orphan library media: marketing_library rows with zero content references.
     *
     * @return list<Media>
     */
    public function orphans(): array
    {
        $usedIds = MarketingContent::query()
            ->whereNotNull('media_id')
            ->pluck('media_id')
            ->unique()
            ->all();

        return Media::query()
            ->where('collection', self::COLLECTION)
            ->when($usedIds !== [], fn ($q) => $q->whereNotIn('id', $usedIds))
            ->orderBy('id')
            ->get()
            ->all();
    }

    private function assertLibraryMedia(Media $media): void
    {
        if (($media->collection ?: '') !== self::COLLECTION) {
            throw ValidationException::withMessages([
                'media' => ['Media is not part of the marketing library.'],
            ]);
        }
    }

    private function assertImageUpload(UploadedFile $upload): void
    {
        $extension = strtolower((string) ($upload->getClientOriginalExtension() ?: $upload->guessExtension() ?: ''));
        $mime = (string) ($upload->getMimeType() ?: '');
        $sizeKb = (int) ceil(((int) $upload->getSize()) / 1024);

        if (! in_array($extension, self::ALLOWED_EXTENSIONS, true)) {
            throw ValidationException::withMessages([
                'file' => ['Only image uploads are allowed for marketing media.'],
            ]);
        }

        if ($mime === '' || ! in_array($mime, self::ALLOWED_MIMES, true)) {
            throw ValidationException::withMessages([
                'file' => ['File MIME type is not allowed.'],
            ]);
        }

        if (! str_starts_with($mime, 'image/')) {
            throw ValidationException::withMessages([
                'file' => ['File must be an image.'],
            ]);
        }

        if ($sizeKb > self::MAX_KILOBYTES) {
            throw ValidationException::withMessages([
                'file' => ['File exceeds the maximum allowed size (8MB).'],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function buildMetadata(UploadedFile $upload, array $attributes): array
    {
        $meta = [
            'alt_text' => is_string($attributes['alt_text'] ?? null) ? $attributes['alt_text'] : '',
            'title' => is_string($attributes['title'] ?? null) ? $attributes['title'] : $this->safeOriginalName($upload),
            'is_active' => array_key_exists('is_active', $attributes) ? (bool) $attributes['is_active'] : true,
            'source_key' => is_string($attributes['source_key'] ?? null) ? $attributes['source_key'] : null,
        ];

        if (isset($attributes['replaced_from'])) {
            $meta['replaced_from'] = (int) $attributes['replaced_from'];
        }

        $dimensions = @getimagesize($upload->getRealPath() ?: '');
        if (is_array($dimensions)) {
            $meta['width'] = (int) $dimensions[0];
            $meta['height'] = (int) $dimensions[1];
        }

        if (is_array($attributes['metadata'] ?? null)) {
            $meta = array_merge($meta, $attributes['metadata']);
        }

        return $meta;
    }

    private function safeOriginalName(UploadedFile $file): string
    {
        $name = basename($file->getClientOriginalName());

        return Str::limit($name === '' ? 'image' : $name, 255, '');
    }

    private function guessMimeFromPath(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }
}
