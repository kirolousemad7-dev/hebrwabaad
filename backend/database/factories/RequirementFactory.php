<?php

namespace Database\Factories;

use App\Enums\RequirementStatus;
use App\Models\Requirement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Requirement>
 */
class RequirementFactory extends Factory
{
    protected $model = Requirement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'RQ-'.now()->format('Y').'-'.fake()->unique()->numerify('####'),
            'name' => fake()->name(),
            'phone' => '05'.fake()->numerify('########'),
            'email' => fake()->unique()->safeEmail(),
            'company' => fake()->optional()->company(),
            'service' => 'هوية وتصميم',
            'category' => 'هوية وتصميم',
            'budget' => '5,000 – 15,000 ر.س',
            'deadline' => 'خلال شهر',
            'description' => fake()->sentence(),
            'attachments' => [],
            'source' => 'needs-discovery',
            'answers' => [
                'need' => ['label' => 'هوية وتصميم', 'value' => 'هوية وتصميم'],
            ],
            'summary' => fake()->sentence(6),
            'recommended_services' => [],
            'status' => RequirementStatus::New,
            'qualified' => false,
        ];
    }
}
