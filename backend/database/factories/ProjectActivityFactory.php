<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ProjectActivity;
use App\Models\User;
use App\Support\ProjectActivityAction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectActivity>
 */
class ProjectActivityFactory extends Factory
{
    protected $model = ProjectActivity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'actor_user_id' => User::factory()->accountManager(),
            'actor_customer_id' => null,
            'action' => ProjectActivityAction::PROJECT_UPDATED,
            'entity_type' => 'project',
            'entity_id' => null,
            'description' => 'Project updated',
            'metadata' => null,
            'is_client_visible' => false,
        ];
    }

    public function clientVisible(): static
    {
        return $this->state(fn (): array => [
            'is_client_visible' => true,
            'action' => ProjectActivityAction::PROJECT_STATUS_CHANGED,
            'description' => 'Project status updated',
        ]);
    }

    public function byCustomer(User $customer): static
    {
        return $this->state(fn (): array => [
            'actor_user_id' => null,
            'actor_customer_id' => $customer->id,
        ]);
    }
}
