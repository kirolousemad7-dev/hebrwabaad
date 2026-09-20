<?php

namespace App\Models;

use Database\Factories\ManagedFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uploaded_by',
    'original_name',
    'stored_name',
    'disk',
    'path',
    'mime_type',
    'extension',
    'size',
    'project_id',
    'order_id',
    'task_id',
    'crm_lead_id',
    'crm_opportunity_id',
    'crm_quotation_id',
    'quote_request_id',
    'commercial_quotation_id',
    'calendar_item_id',
    'is_client_visible',
])]
class ManagedFile extends Model
{
    /** @use HasFactory<ManagedFileFactory> */
    use HasFactory;

    protected $table = 'files';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_client_visible' => 'boolean',
            'size' => 'integer',
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
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<CrmLead, $this>
     */
    public function crmLead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }

    /**
     * @return BelongsTo<CrmOpportunity, $this>
     */
    public function crmOpportunity(): BelongsTo
    {
        return $this->belongsTo(CrmOpportunity::class, 'crm_opportunity_id');
    }

    /**
     * @return BelongsTo<CrmQuotation, $this>
     */
    public function crmQuotation(): BelongsTo
    {
        return $this->belongsTo(CrmQuotation::class, 'crm_quotation_id');
    }

    /**
     * @return BelongsTo<QuoteRequest, $this>
     */
    public function quoteRequest(): BelongsTo
    {
        return $this->belongsTo(QuoteRequest::class, 'quote_request_id');
    }

    /**
     * @return BelongsTo<CommercialQuotation, $this>
     */
    public function commercialQuotation(): BelongsTo
    {
        return $this->belongsTo(CommercialQuotation::class, 'commercial_quotation_id');
    }

    /**
     * @return BelongsTo<CalendarItem, $this>
     */
    public function calendarItem(): BelongsTo
    {
        return $this->belongsTo(CalendarItem::class, 'calendar_item_id');
    }

    public function isPreviewable(): bool
    {
        return str_starts_with($this->mime_type, 'image/')
            || $this->mime_type === 'application/pdf';
    }
}
