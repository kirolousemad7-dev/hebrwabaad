<?php

namespace App\Services\Catalog;

use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\EventRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class EventRequestService
{
    /**
     * @param  array{
     *     event_type: string,
     *     event_date?: string|null,
     *     city?: string|null,
     *     attendance?: int|null,
     *     venue?: string|null,
     *     budget_range?: string|null,
     *     buy_or_rent?: string|null,
     *     notes?: string|null,
     *     consultation_id?: int|null,
     *     create_project?: bool
     * }  $data
     */
    public function create(User $user, array $data): EventRequest
    {
        return DB::transaction(function () use ($user, $data) {
            $createProject = (bool) ($data['create_project'] ?? true);
            unset($data['create_project']);

            $eventRequest = EventRequest::query()->create([
                ...$data,
                'user_id' => $user->id,
                'status' => EventRequest::STATUS_PENDING,
            ]);

            if ($createProject) {
                $manager = User::query()
                    ->where('role', UserRole::AccountManager->value)
                    ->where('is_active', true)
                    ->orderBy('id')
                    ->first()
                    ?? User::query()
                        ->where('role', UserRole::Owner->value)
                        ->where('is_active', true)
                        ->orderBy('id')
                        ->first();

                $project = Project::query()->create([
                    'title' => 'فعالية: '.$eventRequest->event_type,
                    'description' => $eventRequest->notes,
                    'customer_id' => $user->id,
                    'account_manager_id' => $manager?->id ?? $user->id,
                    'status' => ProjectStatus::Planning,
                    'deadline' => $eventRequest->event_date,
                ]);

                $eventRequest->forceFill([
                    'project_id' => $project->id,
                    'status' => EventRequest::STATUS_IN_REVIEW,
                ])->save();
            }

            return $eventRequest->fresh(['project']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(EventRequest $eventRequest, array $data): EventRequest
    {
        $allowedStatuses = [
            EventRequest::STATUS_PENDING,
            EventRequest::STATUS_IN_REVIEW,
            EventRequest::STATUS_CONVERTED,
        ];

        if (isset($data['status']) && ! in_array($data['status'], $allowedStatuses, true)) {
            unset($data['status']);
        }

        $eventRequest->fill($data)->save();

        return $eventRequest->fresh(['project', 'user']);
    }
}
