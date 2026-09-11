<?php

namespace Database\Factories;

use App\Enums\PortfolioMediaType;
use App\Models\PortfolioItem;
use App\Models\PortfolioMedia;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PortfolioMedia>
 */
class PortfolioMediaFactory extends Factory
{
    protected $model = PortfolioMedia::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'portfolio_item_id' => PortfolioItem::factory(),
            'type' => PortfolioMediaType::Image,
            'title' => 'صورة مشروع',
            'caption' => null,
            'url' => '/brand/logo.png',
            'thumbnail_url' => null,
            'display_order' => 0,
            'is_featured' => false,
            'is_public' => true,
        ];
    }

    public function private(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => false,
        ]);
    }

    public function externalVideo(string $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => PortfolioMediaType::ExternalVideo,
            'url' => $url,
            'title' => 'فيديو خارجي',
        ]);
    }
}
