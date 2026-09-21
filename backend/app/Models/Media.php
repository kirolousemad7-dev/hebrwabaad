<?php

namespace App\Models;

use App\Enums\MediaVisibility;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

#[Fillable([
    'disk',
    'path',
    'original_name',
    'mime_type',
    'extension',
    'size',
    'checksum',
    'visibility',
    'uploaded_by',
    'owner_type',
    'owner_id',
    'metadata',
    'collection',
    'sort_order',
    'is_primary',
])]
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'visibility' => MediaVisibility::class,
            'size' => 'integer',
            'metadata' => 'array',
            'sort_order' => 'integer',
            'is_primary' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    public function isPreviewable(): bool
    {
        $mime = (string) $this->mime_type;

        return str_starts_with($mime, 'image/')
            || $mime === 'application/pdf'
            || str_starts_with($mime, 'video/');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function isVideo(): bool
    {
        return str_starts_with((string) $this->mime_type, 'video/');
    }

    public function visibilityEnum(): ?MediaVisibility
    {
        if ($this->visibility instanceof MediaVisibility) {
            return $this->visibility;
        }

        return MediaVisibility::tryFrom((string) $this->visibility);
    }

    /**
     * Absolute public URL for PUBLIC media only. Non-public rows return null.
     *
     * Prefer the public disk (/storage/...) when available; fall back to the
     * authenticated-free file endpoint for legacy PUBLIC rows still on a private disk.
     */
    public function url(): ?string
    {
        if ($this->visibilityEnum() !== MediaVisibility::Public) {
            return null;
        }

        if (($this->disk ?: '') === 'public' && filled($this->path)) {
            $url = Storage::disk('public')->url($this->path);

            if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
                return $url;
            }

            return rtrim((string) config('app.url'), '/').'/'.ltrim($url, '/');
        }

        if (! filled($this->id)) {
            return null;
        }

        return rtrim((string) config('app.url'), '/').'/api/media/'.$this->id.'/file';
    }
}
