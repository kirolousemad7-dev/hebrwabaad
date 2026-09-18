<?php

namespace Database\Factories;

use App\Models\BlogAuthor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlogAuthor>
 */
class BlogAuthorFactory extends Factory
{
    protected $model = BlogAuthor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->name();

        return [
            'name' => $name,
            'slug' => BlogAuthor::uniqueSlug($name),
            'bio' => fake()->optional()->sentence(),
            'avatar_url' => null,
            'email' => fake()->optional()->safeEmail(),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
