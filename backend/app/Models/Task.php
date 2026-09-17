<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

#[Fillable([
    'title',
    'description',
    'project_id',
    'department_id',
    'order_item_id',
    'supplier_id',
    'quotation_supplier_quote_id',
    'commercial_quotation_item_id',
    'source',
    'assigned_to',
    'created_by',
    'priority',
    'status',
    'deadline',
    'calendar_item_id',
])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => TaskPriority::class,
            'status' => TaskStatus::class,
            'deadline' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<OrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /**
     * Internal execution partner — never exposed on customer APIs.
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<QuotationSupplierQuote, $this>
     */
    public function quotationSupplierQuote(): BelongsTo
    {
        return $this->belongsTo(QuotationSupplierQuote::class);
    }

    /**
     * @return BelongsTo<CommercialQuotationItem, $this>
     */
    public function commercialQuotationItem(): BelongsTo
    {
        return $this->belongsTo(CommercialQuotationItem::class);
    }

    /**
     * Soft link to an optional CalendarItem (type=TASK). Explicit only — never auto-created.
     *
     * @return BelongsTo<CalendarItem, $this>
     */
    public function calendarItem(): BelongsTo
    {
        return $this->belongsTo(CalendarItem::class, 'calendar_item_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return MorphToMany<Tag, $this>
     */
    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->withTimestamps();
    }

    public function isOverdue(): bool
    {
        if ($this->deadline === null || $this->status === TaskStatus::Completed) {
            return false;
        }

        return $this->deadline->copy()->endOfDay()->isPast();
    }
}
