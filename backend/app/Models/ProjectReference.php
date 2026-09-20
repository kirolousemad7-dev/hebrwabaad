<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'project_id',
    'title',
    'description',
    'url',
    'type',
    'category',
    'is_client_visible',
    'file_id',
    'created_by',
])]
class ProjectReference extends Model
{
    public const TYPE_DESIGN = 'DESIGN';

    public const TYPE_VIDEO = 'VIDEO';

    public const TYPE_WEBSITE = 'WEBSITE';

    public const TYPE_CONTENT = 'CONTENT';

    public const TYPE_BRANDING = 'BRANDING';

    public const TYPE_OTHER = 'OTHER';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_client_visible' => 'boolean',
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
     * @return BelongsTo<ManagedFile, $this>
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(ManagedFile::class, 'file_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
