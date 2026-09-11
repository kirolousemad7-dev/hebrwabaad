<?php

namespace App\Models;

use App\Enums\ContentStatus;
use Database\Factories\ContentMediaFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContentMedia extends Model
{
    /** @use HasFactory<ContentMediaFactory> */
    use HasFactory, HasUuids;

    protected $table = 'content_media';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uploaded_by',
        'disk',
        'path',
        'thumbnail_path',
        'original_name',
        'mime_type',
        'extension',
        'size',
        'width',
        'height',
        'attachable_type',
        'attachable_id',
        'collection',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
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
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    public function url(?string $variant = null): string
    {
        $base = rtrim((string) config('app.url'), '/');
        $query = $variant === 'thumb' && filled($this->thumbnail_path) ? '?variant=thumb' : '';

        return $base.'/api/content-media/'.$this->id.$query;
    }

    public function storagePath(?string $variant = null): string
    {
        if ($variant === 'thumb' && filled($this->thumbnail_path)) {
            return (string) $this->thumbnail_path;
        }

        return $this->path;
    }

    public function isPublishedParent(): bool
    {
        $parent = $this->attachable;

        if ($parent === null) {
            return false;
        }

        if ($parent instanceof WorkSubmission) {
            return $parent->status === ContentStatus::Published;
        }

        if ($parent instanceof SupplierPortfolioItem) {
            return $parent->status === ContentStatus::Published && $parent->supplier?->isPubliclyVisible() === true;
        }

        if ($parent instanceof SupplierProduct) {
            return $parent->status === ContentStatus::Published && $parent->supplier?->isPubliclyVisible() === true;
        }

        if ($parent instanceof Supplier) {
            return $parent->isPubliclyVisible();
        }

        return false;
    }
}
