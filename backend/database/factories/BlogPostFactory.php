<?php

namespace Database\Factories;

use App\Enums\BlogPostStatus;
use App\Models\BlogAuthor;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    protected $model = BlogPost::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(6);

        return [
            'title' => $title,
            'slug' => BlogPost::uniqueSlug($title),
            'excerpt' => fake()->sentence(12),
            'content' => fake()->paragraphs(3, true),
            'featured_image' => null,
            'author_id' => BlogAuthor::factory(),
            'category_id' => BlogCategory::factory(),
            'status' => BlogPostStatus::Draft,
            'published_at' => null,
            'seo_title' => null,
            'meta_description' => null,
            'og_image' => null,
            'canonical_url' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status' => BlogPostStatus::Published,
            'published_at' => now()->subHour(),
        ]);
    }

    public function scheduled(): static
    {
        return $this->state(fn () => [
            'status' => BlogPostStatus::Scheduled,
            'published_at' => now()->addDay(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => BlogPostStatus::Archived,
            'published_at' => now()->subWeek(),
        ]);
    }
}
