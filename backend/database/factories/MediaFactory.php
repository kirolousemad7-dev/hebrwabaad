<?php

namespace Database\Factories;

use App\Enums\MediaVisibility;
use App\Models\Media;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->lexify('document-????.pdf');
        $stored = Str::uuid()->toString().'.pdf';

        return [
            'disk' => 'local',
            'path' => 'media/task/'.$stored,
            'original_name' => $name,
            'mime_type' => 'application/pdf',
            'extension' => 'pdf',
            'size' => fake()->numberBetween(1_000, 500_000),
            'checksum' => hash('sha256', $stored),
            'visibility' => MediaVisibility::Internal,
            'uploaded_by' => User::factory(),
            'owner_type' => 'task',
            'owner_id' => Task::factory(),
            'metadata' => ['kind' => 'pdf'],
        ];
    }
}
