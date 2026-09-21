<?php

namespace Database\Factories;

use App\Enums\CmsPageType;
use App\Models\CmsPage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CmsPage>
 */
class CmsPageFactory extends Factory
{
    protected $model = CmsPage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('###'),
            'content' => '<p>'.e(fake()->paragraph()).'</p>',
            'page_type' => CmsPageType::General,
            'meta_title' => $title.' | حبر وأبعاد',
            'meta_description' => fake()->sentence(12),
            'meta_keywords' => null,
            'og_title' => null,
            'og_description' => null,
            'og_image' => null,
            'is_published' => true,
            'show_in_footer' => false,
            'footer_group' => null,
            'footer_order' => 0,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['is_published' => false]);
    }

    public function footer(?string $group = 'السياسات', int $order = 1): static
    {
        return $this->state(fn () => [
            'is_published' => true,
            'show_in_footer' => true,
            'footer_group' => $group,
            'footer_order' => $order,
        ]);
    }
}
