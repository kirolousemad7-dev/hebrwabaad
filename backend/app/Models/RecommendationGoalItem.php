<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'recommendation_goal_id',
    'item_type',
    'item_slug',
    'priority',
    'reason_ar',
    'quantity',
    'is_active',
])]
class RecommendationGoalItem extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'priority' => 0,
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'quantity' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<RecommendationGoal, $this>
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(RecommendationGoal::class, 'recommendation_goal_id');
    }

    /**
     * @param  Builder<RecommendationGoalItem>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
