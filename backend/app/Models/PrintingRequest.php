<?php

namespace App\Models;

use App\Enums\PrintingDimensionUnit;
use App\Enums\PrintingMethod;
use App\Enums\PrintingPricingType;
use App\Enums\PrintingQuotationStatus;
use App\Enums\PrintingRequestStatus;
use App\Enums\PrintingShape;
use Database\Factories\PrintingRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'user_id',
    'reordered_from_id',
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
    'status_changed_at',
    'pricing_type',
    'estimated_price',
    'quoted_price',
    'pricing_notes',
    'quoted_at',
    'quoted_by',
    'assigned_to',
    'assigned_department_id',
    'delivery_method',
    'delivery_notes',
    'delivered_at',
    'received_by',
    'payment_policy',
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
            'status_changed_at' => 'datetime',
            'pricing_type' => PrintingPricingType::class,
            'estimated_price' => 'decimal:2',
            'quoted_price' => 'decimal:2',
            'quoted_at' => 'datetime',
            'delivered_at' => 'datetime',
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
     * @return BelongsTo<PrintingRequest, $this>
     */
    public function reorderedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reordered_from_id');
    }

    /**
     * @return HasMany<PrintingRequest, $this>
     */
    public function reorders(): HasMany
    {
        return $this->hasMany(self::class, 'reordered_from_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function quotedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'quoted_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function assignedDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'assigned_department_id');
    }

    /**
     * @return HasMany<PrintingStatusHistory, $this>
     */
    public function statusHistories(): HasMany
    {
        return $this->hasMany(PrintingStatusHistory::class)->orderByDesc('id');
    }

    /**
     * @return HasMany<PrintingQuotation, $this>
     */
    public function quotations(): HasMany
    {
        return $this->hasMany(PrintingQuotation::class);
    }

    /**
     * @return HasMany<PrintingCustomerApproval, $this>
     */
    public function customerApprovals(): HasMany
    {
        return $this->hasMany(PrintingCustomerApproval::class);
    }

    /**
     * @return HasMany<PrintingDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(PrintingDelivery::class);
    }

    /**
     * @return HasOne<PrintingQuotation, $this>
     */
    public function latestAcceptedQuotation(): HasOne
    {
        return $this->hasOne(PrintingQuotation::class)
            ->ofMany(
                ['revision' => 'max', 'id' => 'max'],
                fn ($query) => $query->where('status', PrintingQuotationStatus::Accepted->value),
            );
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

    /**
     * @param  Builder<PrintingRequest>  $query
     */
    public function scopeOpen(Builder $query): void
    {
        $query->whereIn('status', PrintingRequestStatus::openValues());
    }
}
