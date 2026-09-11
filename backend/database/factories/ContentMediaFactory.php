<?php

namespace Database\Factories;

use App\Models\ContentMedia;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ContentMedia>
 */
class ContentMediaFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $uuid = (string) Str::uuid();

        return [
            'id' => $uuid,
            'uploaded_by' => User::factory(),
            'disk' => 'local',
            'path' => 'content/'.$uuid.'.jpg',
            'original_name' => 'cover.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size' => 1024,
            'width' => 800,
            'height' => 600,
            'collection' => 'cover',
        ];
    }
}
