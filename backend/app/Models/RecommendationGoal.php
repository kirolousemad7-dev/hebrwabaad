<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Database\Factories\RecommendationGoalFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'slug',
    'name_ar',
    'explanation',
    'is_active',
    'sort_order',
])]
class RecommendationGoal extends Model
{
    /** @use HasFactory<RecommendationGoalFactory> */
    use HasFactory, HasSlug;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'sort_order' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @return HasMany<RecommendationGoalItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(RecommendationGoalItem::class)
            ->orderBy('priority')
            ->orderBy('id');
    }

    /**
     * @param  Builder<RecommendationGoal>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
