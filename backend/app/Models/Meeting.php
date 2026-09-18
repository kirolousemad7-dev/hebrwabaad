<?php

namespace App\Models;

use App\Enums\MeetingProvider;
use App\Enums\MeetingStatus;
use Database\Factories\MeetingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'provider',
    'meeting_id',
    'join_url',
    'host_url',
    'start_at',
    'end_at',
    'timezone',
    'title',
    'description',
    'status',
    'external_event_id',
    'created_by',
    'task_id',
    'project_id',
    'commercial_quotation_id',
    'supplier_id',
    'customer_id',
    'include_customer',
    'meta',
])]
class Meeting extends Model
{
    /** @use HasFactory<MeetingFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => MeetingProvider::class,
            'status' => MeetingStatus::class,
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'include_customer' => 'boolean',
            'meta' => 'array',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<CommercialQuotation, $this>
     */
    public function commercialQuotation(): BelongsTo
    {
        return $this->belongsTo(CommercialQuotation::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'meeting_participants')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function calendarEventUrl(): ?string
    {
        $link = data_get($this->meta, 'google_html_link');

        return is_string($link) && $link !== '' ? $link : null;
    }
}
