<?php

namespace App\Models\Concerns;

use App\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasMedia
{
    /**
     * Ordered gallery / attachment media for this entity.
     *
     * @return MorphMany<Media, $this>
     */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'owner')
            ->orderByDesc('is_primary')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Primary image/media for list cards (avoids loading full galleries).
     *
     * @return MorphOne<Media, $this>
     */
    public function primaryMedia(): MorphOne
    {
        return $this->morphOne(Media::class, 'owner')->where('is_primary', true);
    }
}
