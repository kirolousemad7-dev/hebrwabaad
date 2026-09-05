<?php

namespace App\Models;

use Database\Factories\PackageTierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'package_id',
    'name',
    'slug',
    'description',
    'price',
    'currency',
    'duration_days',
    'revision_rounds',
    'deliverables',
    'is_active',
    'sort_order',
])]
class PackageTier extends Model
{
    /** @use HasFactory<PackageTierFactory> */
    use HasFactory;

    /**
     * Mirrors the database defaults so freshly created models report them.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'currency' => 'SAR',
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'duration_days' => 'integer',
            'revision_rounds' => 'integer',
            'deliverables' => 'array',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Package, $this>
     */
    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * A tier is only chargeable once the owner has entered its price.
     */
    public function isPriced(): bool
    {
        return $this->price !== null && (float) $this->price > 0;
    }

    /**
     * @param  Builder<PackageTier>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
