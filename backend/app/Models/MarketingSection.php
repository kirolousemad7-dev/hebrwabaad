<?php

namespace App\Models;

use App\Enums\MarketingSectionType;
use App\Models\Concerns\HasMedia;
use Database\Factories\MarketingSectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'key',
    'admin_title',
    'type',
    'is_enabled',
    'sort_order',
    'config',
])]
class MarketingSection extends Model
{
    /** @use HasFactory<MarketingSectionFactory> */
    use HasFactory;

    use HasMedia;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_enabled' => true,
        'sort_order' => 0,
        'type' => 'custom',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => MarketingSectionType::class,
            'is_enabled' => 'boolean',
            'sort_order' => 'integer',
            'config' => 'array',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    /**
     * @return HasMany<MarketingContent, $this>
     */
    public function contents(): HasMany
    {
        return $this->hasMany(MarketingContent::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * @param  Builder<MarketingSection>  $query
     * @return Builder<MarketingSection>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }
}
