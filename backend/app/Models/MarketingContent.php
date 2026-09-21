<?php

namespace App\Models;

use App\Support\HtmlSanitizer;
use Database\Factories\MarketingContentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'marketing_section_id',
    'content_key',
    'value_text',
    'value_html',
    'media_id',
    'sort_order',
    'is_enabled',
    'metadata',
])]
class MarketingContent extends Model
{
    /** @use HasFactory<MarketingContentFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_enabled' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function setValueHtmlAttribute(?string $value): void
    {
        $this->attributes['value_html'] = $value === null || $value === ''
            ? null
            : HtmlSanitizer::clean($value);
    }

    /**
     * @return BelongsTo<MarketingSection, $this>
     */
    public function section(): BelongsTo
    {
        return $this->belongsTo(MarketingSection::class, 'marketing_section_id');
    }

    /**
     * Reuses the platform Media table as the marketing media library (MediaAsset).
     *
     * @return BelongsTo<Media, $this>
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * @param  Builder<MarketingContent>  $query
     * @return Builder<MarketingContent>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }
}
