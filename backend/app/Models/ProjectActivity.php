<?php

namespace App\Models;

use Database\Factories\ProjectActivityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id',
    'actor_user_id',
    'actor_customer_id',
    'action',
    'entity_type',
    'entity_id',
    'description',
    'metadata',
    'is_client_visible',
])]
class ProjectActivity extends Model
{
    /** @use HasFactory<ProjectActivityFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_client_visible' => 'boolean',
            'entity_id' => 'integer',
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
     * Staff / owner actor.
     *
     * @return BelongsTo<User, $this>
     */
    public function actorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /**
     * Customer actor (users with Customer role).
     *
     * @return BelongsTo<User, $this>
     */
    public function actorCustomer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_customer_id');
    }
}
