<?php

namespace App\Models;

use App\Enums\RequirementStatus;
use Database\Factories\RequirementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reference',
    'name',
    'phone',
    'email',
    'company',
    'service',
    'category',
    'budget',
    'deadline',
    'description',
    'attachments',
    'source',
    'answers',
    'summary',
    'recommended_services',
    'status',
    'assigned_to',
    'crm_lead_id',
    'task_id',
    'service_id',
    'notes',
    'qualified',
    'qualified_at',
])]
class Requirement extends Model
{
    /** @use HasFactory<RequirementFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'source' => 'needs-discovery',
        'status' => 'NEW',
        'qualified' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RequirementStatus::class,
            'attachments' => 'array',
            'answers' => 'array',
            'recommended_services' => 'array',
            'qualified' => 'boolean',
            'qualified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /**
     * @return BelongsTo<CrmLead, $this>
     */
    public function crmLead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'crm_lead_id');
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function catalogService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'service_id');
    }
}
