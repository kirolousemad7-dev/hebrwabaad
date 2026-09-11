<?php

namespace Database\Factories;

use App\Models\SeoPage;
use App\Support\SeoPages;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SeoPage>
 */
class SeoPageFactory extends Factory
{
    protected $model = SeoPage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'page_key' => fake()->unique()->randomElement(SeoPages::KEYS),
            'title' => 'حبر وأبعاد',
            'description' => 'وكالة إبداعية للبرمجة والتسويق والطباعة وتنظيم الفعاليات.',
            'keywords' => 'حبر وأبعاد, تسويق, تصميم, طباعة',
            'canonical_url' => null,
            'og_title' => 'حبر وأبعاد',
            'og_description' => 'وكالة إبداعية للبرمجة والتسويق والطباعة وتنظيم الفعاليات.',
            'og_image' => '/brand/logo.png',
            'twitter_title' => 'حبر وأبعاد',
            'twitter_description' => 'وكالة إبداعية للبرمجة والتسويق والطباعة وتنظيم الفعاليات.',
            'twitter_image' => '/brand/logo.png',
            'robots' => 'index,follow',
            'schema_json' => null,
        ];
    }
}
