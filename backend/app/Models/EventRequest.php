<?php

namespace App\Models;

use Database\Factories\EventRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id',
    'event_type',
    'event_date',
    'city',
    'attendance',
    'venue',
    'budget_range',
    'buy_or_rent',
    'notes',
    'status',
    'project_id',
    'consultation_id',
])]
class EventRequest extends Model
{
    /** @use HasFactory<EventRequestFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'PENDING';

    public const STATUS_IN_REVIEW = 'IN_REVIEW';

    public const STATUS_CONVERTED = 'CONVERTED';

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => self::STATUS_PENDING,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event_date' => 'date',
            'attendance' => 'integer',
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
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Consultation, $this>
     */
    public function consultation(): BelongsTo
    {
        return $this->belongsTo(Consultation::class);
    }
}
