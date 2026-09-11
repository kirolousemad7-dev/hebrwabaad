<?php

namespace App\Services\Portfolio;

use App\Enums\CatalogPricingMode;
use App\Enums\PortfolioCategory;
use App\Enums\PortfolioMediaType;
use App\Models\Package;
use App\Models\PortfolioItem;
use App\Models\PortfolioMedia;
use App\Models\Service;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PortfolioShowcaseService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, PortfolioItem>
     */
    public function listPublished(array $filters = []): Collection
    {
        $category = $filters['category'] ?? null;
        $sector = $filters['sector'] ?? null;
        $service = $filters['service'] ?? null;

        return PortfolioItem::query()
            ->published()
            ->with([
                'sectors:id,name_ar,name_en,slug',
                'services:id,name,slug,pricing_mode',
                'publicMedia',
            ])
            ->when(
                is_string($category) && in_array($category, PortfolioCategory::values(), true),
                fn ($query) => $query->where('category', $category),
            )
            ->when(is_string($sector) && $sector !== '', function ($query) use ($sector) {
                $query->whereHas('sectors', fn ($q) => $q->where('slug', $sector));
            })
            ->when(is_string($service) && $service !== '', function ($query) use ($service) {
                $query->whereHas('services', fn ($q) => $q->where('slug', $service));
            })
            ->get();
    }

    public function findPublishedBySlug(string $slug): PortfolioItem
    {
        return PortfolioItem::query()
            ->published()
            ->where('slug', $slug)
            ->with([
                'sectors:id,name_ar,name_en,slug',
                'services:id,name,slug,pricing_mode,summary,is_public,is_active',
                'package:id,name,slug,description,is_public,is_active',
                'publicMedia',
            ])
            ->firstOrFail();
    }

    /**
     * @return array<string, mixed>
     */
    public function listingPayload(PortfolioItem $item): array
    {
        $mediaType = $this->resolvePrimaryMediaType($item);
        $thumbnail = $this->listingThumbnail($item);

        return [
            'id' => $item->id,
            'slug' => $item->slug,
            'title' => $item->title,
            'brand_name' => $item->brand_name,
            'category' => $item->category instanceof PortfolioCategory
                ? $item->category->value
                : (string) $item->category,
            'description' => $item->short_description ?: $item->description,
            'short_description' => $item->short_description,
            'tags' => $item->tags ?? [],
            'image_url' => $item->image_url,
            'cover_url' => $thumbnail,
            'media_type' => $mediaType?->value,
            'is_sample' => $item->is_sample,
            'is_featured' => (bool) $item->is_featured,
            'sort_order' => $item->sort_order,
            'has_video' => $mediaType?->isVideo() ?? false,
            'sectors' => $item->sectors->map(fn ($sector) => [
                'id' => $sector->id,
                'name' => $sector->name_ar ?: $sector->name_en,
                'slug' => $sector->slug,
            ])->values()->all(),
            'services' => $item->services->map(fn ($service) => [
                'id' => $service->id,
                'name' => $service->name,
                'slug' => $service->slug,
            ])->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function detailPayload(PortfolioItem $item): array
    {
        $media = $item->publicMedia
            ->map(fn (PortfolioMedia $row) => $this->mediaPayload($row))
            ->filter()
            ->values()
            ->all();

        $package = $this->publicPackagePayload($item->package);
        $services = $item->services
            ->filter(fn (Service $service) => (bool) $service->is_public && (bool) $service->is_active)
            ->values();

        $cta = $this->ctaContext($item, $services, $package);
        $related = $this->relatedWorks($item);

        return [
            'id' => $item->id,
            'slug' => $item->slug,
            'title' => $item->title,
            'brand_name' => $item->brand_name,
            'category' => $item->category instanceof PortfolioCategory
                ? $item->category->value
                : (string) $item->category,
            'description' => $item->description,
            'short_description' => $item->short_description,
            'challenge' => $item->challenge,
            'solution' => $item->solution,
            'execution' => $item->execution,
            'deliverables' => $item->deliverables ?? [],
            'results' => filled($item->results) ? $item->results : null,
            'tags' => $item->tags ?? [],
            'image_url' => $item->image_url,
            'project_url' => $this->sanitizePublicUrl($item->project_url),
            'video_url' => null,
            'primary_media_type' => $this->resolvePrimaryMediaType($item)?->value,
            'is_sample' => $item->is_sample,
            'is_featured' => (bool) $item->is_featured,
            'sort_order' => $item->sort_order,
            'sectors' => $item->sectors->map(fn ($sector) => [
                'id' => $sector->id,
                'name' => $sector->name_ar ?: $sector->name_en,
                'slug' => $sector->slug,
            ])->values()->all(),
            'services' => $services->map(fn (Service $service) => [
                'id' => $service->id,
                'name' => $service->name,
                'slug' => $service->slug,
                'summary' => $service->summary,
                'pricing_mode' => $service->pricing_mode instanceof CatalogPricingMode
                    ? $service->pricing_mode->value
                    : (string) $service->pricing_mode,
            ])->values()->all(),
            'package' => $package,
            'media' => $media,
            'cta' => $cta,
            'related' => $related,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function staffPayload(PortfolioItem $item): array
    {
        $item->loadMissing([
            'sectors:id,name_ar,name_en,slug',
            'services:id,name,slug,pricing_mode,summary,is_public,is_active',
            'package:id,name,slug,description,is_public,is_active',
            'media',
            'publicMedia',
        ]);

        $public = $this->detailPayload($item);
        $public['is_published'] = $item->is_published;
        $public['updated_at'] = $item->updated_at?->toIso8601String();
        $public['service_ids'] = $item->services->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $public['sector_ids'] = $item->sectors->pluck('id')->map(fn ($id) => (int) $id)->values()->all();
        $public['package_id'] = $item->package_id;
        $public['video_url'] = $this->sanitizePublicUrl($item->video_url);
        $public['services'] = $item->services->map(fn (Service $service) => [
            'id' => $service->id,
            'name' => $service->name,
            'slug' => $service->slug,
            'summary' => $service->summary,
            'pricing_mode' => $service->pricing_mode instanceof CatalogPricingMode
                ? $service->pricing_mode->value
                : (string) $service->pricing_mode,
        ])->values()->all();
        $public['media'] = $item->media
            ->map(fn (PortfolioMedia $row) => $this->mediaPayload($row, includePrivate: true))
            ->filter()
            ->values()
            ->all();

        return $public;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function upsert(array $data, ?PortfolioItem $item = null): PortfolioItem
    {
        return DB::transaction(function () use ($data, $item) {
            $creating = $item === null;
            $item ??= new PortfolioItem;

            $title = (string) ($data['title'] ?? $item->title ?? '');
            $slugInput = isset($data['slug']) ? trim((string) $data['slug']) : null;
            $slug = $slugInput !== null && $slugInput !== ''
                ? PortfolioItem::uniqueSlug($slugInput, $item->id)
                : ($item->slug ?: PortfolioItem::uniqueSlug($title, $item->id));

            $attributes = [
                'title' => $title,
                'slug' => $slug,
                'brand_name' => $data['brand_name'] ?? $item->brand_name,
                'category' => $data['category'] ?? $item->category,
                'description' => $data['description'] ?? $item->description,
                'short_description' => $data['short_description'] ?? $item->short_description,
                'challenge' => $data['challenge'] ?? $item->challenge,
                'solution' => $data['solution'] ?? $item->solution,
                'execution' => $data['execution'] ?? $item->execution,
                'deliverables' => $data['deliverables'] ?? $item->deliverables,
                'results' => $data['results'] ?? $item->results,
                'tags' => $data['tags'] ?? $item->tags,
                'image_url' => $data['image_url'] ?? $item->image_url,
                'project_url' => array_key_exists('project_url', $data)
                    ? $this->sanitizePublicUrl($data['project_url'] ?? null)
                    : $item->project_url,
                'video_url' => array_key_exists('video_url', $data)
                    ? $this->sanitizePublicUrl($data['video_url'] ?? null)
                    : $item->video_url,
                'primary_media_type' => $data['primary_media_type'] ?? $item->primary_media_type,
                'is_sample' => array_key_exists('is_sample', $data) ? (bool) $data['is_sample'] : ($item->is_sample ?? true),
                'is_published' => array_key_exists('is_published', $data) ? (bool) $data['is_published'] : ($item->is_published ?? true),
                'is_featured' => array_key_exists('is_featured', $data) ? (bool) $data['is_featured'] : ($item->is_featured ?? false),
                'sort_order' => array_key_exists('sort_order', $data) ? (int) $data['sort_order'] : ($item->sort_order ?? 0),
                'package_id' => array_key_exists('package_id', $data) ? $data['package_id'] : $item->package_id,
            ];

            if ($creating && empty($attributes['image_url'])) {
                throw ValidationException::withMessages([
                    'image_url' => ['صورة الغلاف مطلوبة.'],
                ]);
            }

            $item->fill($attributes);
            $item->save();

            if (array_key_exists('service_ids', $data) && is_array($data['service_ids'])) {
                $item->services()->sync(collect($data['service_ids'])->map(fn ($id) => (int) $id)->filter()->unique()->all());
            }

            if (array_key_exists('sector_ids', $data) && is_array($data['sector_ids'])) {
                $item->sectors()->sync(collect($data['sector_ids'])->map(fn ($id) => (int) $id)->filter()->unique()->all());
            }

            if (array_key_exists('media', $data) && is_array($data['media'])) {
                $this->syncMedia($item, $data['media']);
            } elseif ($creating && filled($item->video_url)) {
                $this->syncMedia($item, [[
                    'type' => PortfolioMediaType::ExternalVideo->value,
                    'url' => $item->video_url,
                    'title' => 'فيديو المشروع',
                    'is_featured' => true,
                    'display_order' => 0,
                    'is_public' => true,
                ]]);
            }

            return $item->fresh([
                'sectors',
                'services',
                'package',
                'media',
            ]);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public function syncMedia(PortfolioItem $item, array $rows): void
    {
        $keepIds = [];

        foreach (array_values($rows) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $typeValue = (string) ($row['type'] ?? '');
            $type = PortfolioMediaType::tryFrom($typeValue);
            if ($type === null) {
                throw ValidationException::withMessages([
                    'media' => ['نوع الوسائط غير مدعوم.'],
                ]);
            }

            $url = $this->sanitizeMediaUrl($type, $row['url'] ?? null);
            $thumbnailUrl = $this->sanitizePublicUrl($row['thumbnail_url'] ?? null);
            $contentMediaId = $row['content_media_id'] ?? null;
            $thumbnailMediaId = $row['thumbnail_media_id'] ?? null;

            if ($type === PortfolioMediaType::UploadedVideo || $type === PortfolioMediaType::Image) {
                if ($url === null && $contentMediaId === null) {
                    throw ValidationException::withMessages([
                        'media' => ['عنصر الوسائط يحتاج رابطاً أو ملفاً.'],
                    ]);
                }
            }

            if ($type->isLink() || $type === PortfolioMediaType::ExternalVideo) {
                if ($url === null) {
                    throw ValidationException::withMessages([
                        'media' => ['رابط الوسائط مطلوب.'],
                    ]);
                }
            }

            if ($type === PortfolioMediaType::ExternalVideo && $this->externalVideoEmbed($url) === null) {
                throw ValidationException::withMessages([
                    'media' => ['رابط الفيديو الخارجي غير مدعوم. استخدم YouTube أو Vimeo فقط.'],
                ]);
            }

            $payload = [
                'type' => $type,
                'title' => isset($row['title']) ? Str::limit((string) $row['title'], 255, '') : null,
                'caption' => isset($row['caption']) ? Str::limit((string) $row['caption'], 1000, '') : null,
                'url' => $url,
                'content_media_id' => $contentMediaId ?: null,
                'thumbnail_media_id' => $thumbnailMediaId ?: null,
                'thumbnail_url' => $thumbnailUrl,
                'display_order' => (int) ($row['display_order'] ?? $index),
                'is_featured' => (bool) ($row['is_featured'] ?? false),
                'is_public' => array_key_exists('is_public', $row) ? (bool) $row['is_public'] : true,
            ];

            $mediaId = isset($row['id']) ? (int) $row['id'] : null;
            if ($mediaId) {
                $media = PortfolioMedia::query()
                    ->where('portfolio_item_id', $item->id)
                    ->whereKey($mediaId)
                    ->first();
                if ($media) {
                    $media->update($payload);
                    $keepIds[] = $media->id;

                    continue;
                }
            }

            $created = $item->media()->create($payload);
            $keepIds[] = $created->id;
        }

        $item->media()->whereNotIn('id', $keepIds)->delete();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function mediaPayload(PortfolioMedia $media, bool $includePrivate = false): ?array
    {
        if (! $includePrivate && ! $media->is_public) {
            return null;
        }

        $type = $media->type instanceof PortfolioMediaType
            ? $media->type
            : PortfolioMediaType::tryFrom((string) $media->type);

        if ($type === null) {
            return null;
        }

        $url = $this->sanitizeMediaUrl($type, $media->url);
        $thumbnail = $this->sanitizePublicUrl($media->thumbnail_url);

        $embed = $type === PortfolioMediaType::ExternalVideo && $url
            ? $this->externalVideoEmbed($url)
            : null;

        return [
            'id' => $media->id,
            'type' => $type->value,
            'title' => $media->title,
            'caption' => $media->caption,
            'url' => $url,
            'thumbnail_url' => $thumbnail,
            'display_order' => $media->display_order,
            'is_featured' => $media->is_featured,
            'is_public' => $media->is_public,
            'embed' => $embed,
            'provider' => $embed['provider'] ?? null,
        ];
    }

    /**
     * @return array{provider: string, embed_url: string, watch_url: string}|null
     */
    public function externalVideoEmbed(?string $url): ?array
    {
        $safe = $this->sanitizePublicUrl($url);
        if ($safe === null) {
            return null;
        }

        $host = strtolower((string) parse_url($safe, PHP_URL_HOST));
        $host = Str::of($host)->replaceStart('www.', '')->toString();
        $path = (string) parse_url($safe, PHP_URL_PATH);
        $query = [];
        parse_str((string) parse_url($safe, PHP_URL_QUERY), $query);

        if (in_array($host, ['youtube.com', 'm.youtube.com', 'youtube-nocookie.com'], true)) {
            $id = null;
            if (str_starts_with($path, '/embed/')) {
                $id = trim(substr($path, 7), '/');
            } elseif (str_starts_with($path, '/shorts/')) {
                $id = trim(substr($path, 8), '/');
            } elseif (isset($query['v']) && is_string($query['v'])) {
                $id = $query['v'];
            }
            if ($id && preg_match('/^[A-Za-z0-9_-]{6,32}$/', $id)) {
                return [
                    'provider' => 'youtube',
                    'embed_url' => 'https://www.youtube-nocookie.com/embed/'.$id,
                    'watch_url' => 'https://www.youtube.com/watch?v='.$id,
                ];
            }
        }

        if ($host === 'youtu.be') {
            $id = trim($path, '/');
            if ($id && preg_match('/^[A-Za-z0-9_-]{6,32}$/', $id)) {
                return [
                    'provider' => 'youtube',
                    'embed_url' => 'https://www.youtube-nocookie.com/embed/'.$id,
                    'watch_url' => 'https://www.youtube.com/watch?v='.$id,
                ];
            }
        }

        if (in_array($host, ['vimeo.com', 'player.vimeo.com'], true)) {
            if (preg_match('#/(?:video/)?(\d+)#', $path, $matches)) {
                $id = $matches[1];

                return [
                    'provider' => 'vimeo',
                    'embed_url' => 'https://player.vimeo.com/video/'.$id,
                    'watch_url' => 'https://vimeo.com/'.$id,
                ];
            }
        }

        return null;
    }

    public function sanitizePublicUrl(mixed $url): ?string
    {
        if ($url === null) {
            return null;
        }

        $value = trim((string) $url);
        if ($value === '') {
            return null;
        }

        if (preg_match('#^(javascript|data|vbscript):#i', $value)) {
            return null;
        }

        if (str_starts_with($value, '/')) {
            if (str_starts_with($value, '//')) {
                return null;
            }

            return Str::limit($value, 2048, '');
        }

        if (! preg_match('#^https://#i', $value)) {
            return null;
        }

        return Str::limit($value, 2048, '');
    }

    public function sanitizeMediaUrl(PortfolioMediaType $type, mixed $url): ?string
    {
        $safe = $this->sanitizePublicUrl($url);
        if ($safe === null) {
            return null;
        }

        if ($type === PortfolioMediaType::UploadedVideo) {
            $path = parse_url($safe, PHP_URL_PATH) ?: $safe;
            $ext = strtolower(pathinfo((string) $path, PATHINFO_EXTENSION));
            if ($ext !== '' && ! in_array($ext, ['mp4', 'webm'], true)) {
                throw ValidationException::withMessages([
                    'media' => ['يُسمح فقط بملفات فيديو MP4 أو WebM.'],
                ]);
            }
        }

        return $safe;
    }

    private function resolvePrimaryMediaType(PortfolioItem $item): ?PortfolioMediaType
    {
        if ($item->primary_media_type instanceof PortfolioMediaType) {
            return $item->primary_media_type;
        }

        $featured = $item->relationLoaded('publicMedia')
            ? $item->publicMedia->firstWhere('is_featured', true) ?? $item->publicMedia->first()
            : null;

        if ($featured?->type instanceof PortfolioMediaType) {
            return $featured->type;
        }

        if (filled($item->video_url)) {
            return PortfolioMediaType::ExternalVideo;
        }

        if (filled($item->project_url)) {
            return PortfolioMediaType::WebsiteLink;
        }

        return PortfolioMediaType::Image;
    }

    private function listingThumbnail(PortfolioItem $item): ?string
    {
        $featured = $item->relationLoaded('publicMedia')
            ? $item->publicMedia->firstWhere('is_featured', true) ?? $item->publicMedia->first()
            : null;

        if ($featured) {
            $thumb = $this->sanitizePublicUrl($featured->thumbnail_url);
            if ($thumb) {
                return $thumb;
            }
            if ($featured->type === PortfolioMediaType::Image) {
                return $this->sanitizePublicUrl($featured->url) ?? $item->image_url;
            }
        }

        return $item->image_url;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function publicPackagePayload(?Package $package): ?array
    {
        if ($package === null || ! $package->is_public || ! $package->is_active) {
            return null;
        }

        return [
            'id' => $package->id,
            'name' => $package->name,
            'slug' => $package->slug,
            'summary' => $package->description,
        ];
    }

    /**
     * @param  Collection<int, Service>  $services
     * @param  array<string, mixed>|null  $package
     * @return array<string, mixed>
     */
    private function ctaContext(PortfolioItem $item, Collection $services, ?array $package): array
    {
        $sectorSlug = $item->sectors->first()?->slug;
        $serviceSlugs = $services->pluck('slug')->filter()->values()->all();
        $hasQuoteService = $services->contains(function (Service $service): bool {
            $mode = $service->pricing_mode;

            return $mode === CatalogPricingMode::Quote
                || (is_string($mode) && $mode === CatalogPricingMode::Quote->value);
        });

        $similarQuery = array_filter([
            'portfolio' => $item->slug,
            'case' => (string) $item->id,
            'sector' => $sectorSlug,
            'services' => $serviceSlugs !== [] ? implode(',', $serviceSlugs) : null,
            'package' => $package['slug'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        $buildPackagePath = '/build-package';
        if ($serviceSlugs !== []) {
            $buildPackagePath .= '?services='.rawurlencode(implode(',', $serviceSlugs));
        }

        $consultantPath = '/consultant?'.http_build_query($similarQuery);

        $quotePayload = [
            'case_study_id' => $item->id,
            'portfolio_slug' => $item->slug,
            'portfolio_title' => $item->title,
            'sector_slug' => $sectorSlug,
            'service_ids' => $services->pluck('id')->values()->all(),
            'service_slugs' => $serviceSlugs,
            'package_id' => $package['id'] ?? null,
            'package_slug' => $package['slug'] ?? null,
        ];

        $quotePath = '/request-quote?'.http_build_query([
            'source_type' => 'PORTFOLIO',
            'source_id' => $item->id,
            'title' => 'مشروع مشابه: '.$item->title,
            'payload' => json_encode($quotePayload, JSON_UNESCAPED_UNICODE),
        ]);

        return [
            'similar_path' => $serviceSlugs !== [] ? $buildPackagePath : $consultantPath,
            'consultant_path' => $consultantPath,
            'build_package_path' => $buildPackagePath,
            'quote_path' => $hasQuoteService || $serviceSlugs === [] ? $quotePath : null,
            'show_quote_cta' => $hasQuoteService,
            'context' => [
                'portfolio_id' => $item->id,
                'portfolio_slug' => $item->slug,
                'sector_slug' => $sectorSlug,
                'service_slugs' => $serviceSlugs,
                'package_slug' => $package['slug'] ?? null,
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function relatedWorks(PortfolioItem $item): array
    {
        $sectorIds = $item->sectors->pluck('id');
        $serviceIds = $item->services->pluck('id');

        $related = PortfolioItem::query()
            ->published()
            ->whereKeyNot($item->id)
            ->with(['sectors:id,name_ar,name_en,slug', 'services:id,name,slug', 'publicMedia'])
            ->where(function ($query) use ($sectorIds, $serviceIds, $item) {
                $query->where('category', $item->category);
                if ($sectorIds->isNotEmpty()) {
                    $query->orWhereHas('sectors', fn ($q) => $q->whereIn('sectors.id', $sectorIds));
                }
                if ($serviceIds->isNotEmpty()) {
                    $query->orWhereHas('services', fn ($q) => $q->whereIn('services.id', $serviceIds));
                }
            })
            ->limit(6)
            ->get();

        return $related->map(fn (PortfolioItem $row) => $this->listingPayload($row))->values()->all();
    }
}
