<?php

namespace App\Models;

use App\Enums\PrintingDimensionUnit;
use App\Enums\PrintingMethod;
use App\Enums\PrintingPricingType;
use App\Enums\PrintingRequestStatus;
use App\Enums\PrintingShape;
use Database\Factories\PrintingRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'product_slug',
    'product_name',
    'width',
    'height',
    'dimension_unit',
    'shape',
    'material',
    'quantity',
    'printing_method',
    'finishing',
    'file_path',
    'original_filename',
    'required_date',
    'notes',
    'status',
    'pricing_type',
    'estimated_price',
    'quoted_price',
    'pricing_notes',
    'quoted_at',
    'quoted_by',
])]
class PrintingRequest extends Model
{
    /** @use HasFactory<PrintingRequestFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'PENDING',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'width' => 'decimal:2',
            'height' => 'decimal:2',
            'quantity' => 'integer',
            'dimension_unit' => PrintingDimensionUnit::class,
            'shape' => PrintingShape::class,
            'printing_method' => PrintingMethod::class,
            'finishing' => 'array',
            'required_date' => 'date',
            'status' => PrintingRequestStatus::class,
            'pricing_type' => PrintingPricingType::class,
            'estimated_price' => 'decimal:2',
            'quoted_price' => 'decimal:2',
            'quoted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function quotedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'quoted_by');
    }

    /**
     * Requests still waiting for an owner/reviewer pricing action.
     *
     * @param  Builder<PrintingRequest>  $query
     */
    public function scopeNeedsOwnerAttention(Builder $query): void
    {
        $query->where(function (Builder $inner): void {
            $inner->whereNull('pricing_type')
                ->orWhere('pricing_type', PrintingPricingType::QuoteRequired);
        });
    }
}
