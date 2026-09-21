<?php

namespace App\Models;

use App\Enums\CmsPageType;
use App\Models\Concerns\HasSlug;
use App\Support\HtmlSanitizer;
use Database\Factories\CmsPageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'title',
    'slug',
    'content',
    'page_type',
    'meta_title',
    'meta_description',
    'meta_keywords',
    'og_title',
    'og_description',
    'og_image',
    'is_published',
    'show_in_footer',
    'footer_group',
    'footer_order',
])]
class CmsPage extends Model
{
    /** @use HasFactory<CmsPageFactory> */
    use HasFactory;

    use HasSlug;

    /**
     * Seeded core public pages — routes/footer expect these slugs.
     *
     * @var list<string>
     */
    public const SYSTEM_SLUGS = [
        'about',
        'contact',
        'golden-warranty',
        'terms-and-conditions',
        'privacy-policy',
        'returns-and-refunds',
        'shipping-policy',
        'services-and-products-policies',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'page_type' => 'GENERAL',
        'is_published' => false,
        'show_in_footer' => false,
        'footer_order' => 0,
        'content' => '',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page_type' => CmsPageType::class,
            'is_published' => 'boolean',
            'show_in_footer' => 'boolean',
            'footer_order' => 'integer',
        ];
    }

    public function isSystem(): bool
    {
        return in_array((string) $this->slug, self::SYSTEM_SLUGS, true);
    }

    public function setContentAttribute(?string $value): void
    {
        $this->attributes['content'] = HtmlSanitizer::clean($value ?? '');
    }

    /**
     * @param  Builder<CmsPage>  $query
     * @return Builder<CmsPage>
     */
    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    /**
     * @param  Builder<CmsPage>  $query
     * @return Builder<CmsPage>
     */
    public function scopeInFooter(Builder $query): Builder
    {
        return $query
            ->published()
            ->where('show_in_footer', true)
            ->orderBy('footer_group')
            ->orderBy('footer_order')
            ->orderBy('title');
    }

    public function seoTitle(): string
    {
        return filled($this->meta_title) ? (string) $this->meta_title : (string) $this->title;
    }

    public function seoDescription(): ?string
    {
        return filled($this->meta_description) ? (string) $this->meta_description : null;
    }

    public function publicPath(): string
    {
        return '/'.ltrim((string) $this->slug, '/');
    }
}
