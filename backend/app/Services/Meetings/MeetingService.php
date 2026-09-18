<?php

namespace App\Services\Meetings;

use App\Enums\MeetingProvider;
use App\Enums\MeetingStatus;
use App\Enums\UserRole;
use App\Models\CommercialQuotation;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MeetingService
{
    public function __construct(
        private readonly VideoMeetingProviderManager $providers,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(User $actor, array $data): Meeting
    {
        $provider = MeetingProvider::from((string) $data['provider']);
        $this->assertCanManage($actor, $data);

        $driver = $this->providers->assertAvailable($provider);

        $participantIds = $this->normalizeParticipantIds($data['participant_ids'] ?? []);
        $attendeeEmails = $this->resolveAttendeeEmails($participantIds, $data);

        $remote = $driver->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'start_at' => $data['start_at'],
            'end_at' => $data['end_at'] ?? null,
            'timezone' => $data['timezone'] ?? config('app.timezone'),
            'host_user_id' => $actor->id,
            'attendee_emails' => $attendeeEmails,
        ]);

        return DB::transaction(function () use ($actor, $data, $provider, $remote, $participantIds): Meeting {
            $meeting = Meeting::query()->create([
                'provider' => $provider,
                'meeting_id' => $remote['meeting_id'] ?? null,
                'join_url' => $remote['join_url'] ?? null,
                'host_url' => $remote['host_url'] ?? null,
                'start_at' => $data['start_at'],
                'end_at' => $data['end_at'] ?? null,
                'timezone' => $data['timezone'] ?? config('app.timezone'),
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => MeetingStatus::Scheduled,
                'external_event_id' => $remote['external_event_id'] ?? null,
                'created_by' => $actor->id,
                'task_id' => $data['task_id'] ?? null,
                'project_id' => $data['project_id'] ?? null,
                'commercial_quotation_id' => $data['commercial_quotation_id'] ?? null,
                'supplier_id' => $data['supplier_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'include_customer' => (bool) ($data['include_customer'] ?? false),
                'meta' => $remote['meta'] ?? [],
            ]);

            $sync = [$actor->id => ['role' => 'host']];
            foreach ($participantIds as $userId) {
                if ((int) $userId === (int) $actor->id) {
                    continue;
                }
                $sync[(int) $userId] = ['role' => 'attendee'];
            }
            $meeting->participants()->sync($sync);

            return $meeting->load(['participants:id,name,email,role', 'creator:id,name,email', 'task:id,title', 'project:id,title', 'supplier:id,name,display_name']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(User $actor, Meeting $meeting, array $data): Meeting
    {
        $this->assertCanManageMeeting($actor, $meeting);

        $provider = $meeting->provider instanceof MeetingProvider
            ? $meeting->provider
            : MeetingProvider::from((string) $meeting->provider);

        if ($provider->createsRemoteSession() && filled($meeting->meeting_id)) {
            $driver = $this->providers->assertAvailable($provider);
            $remote = $driver->update((string) $meeting->meeting_id, [
                'title' => $data['title'] ?? $meeting->title,
                'description' => $data['description'] ?? $meeting->description,
                'start_at' => $data['start_at'] ?? $meeting->start_at?->toIso8601String(),
                'end_at' => $data['end_at'] ?? $meeting->end_at?->toIso8601String(),
                'timezone' => $data['timezone'] ?? $meeting->timezone,
                'host_user_id' => $actor->id,
            ], $meeting->external_event_id);

            $meeting->fill([
                'meeting_id' => $remote['meeting_id'] ?? $meeting->meeting_id,
                'join_url' => $remote['join_url'] ?? $meeting->join_url,
                'host_url' => $remote['host_url'] ?? $meeting->host_url,
                'external_event_id' => $remote['external_event_id'] ?? $meeting->external_event_id,
                'meta' => array_merge($meeting->meta ?? [], $remote['meta'] ?? []),
            ]);
        }

        $meeting->fill([
            'title' => $data['title'] ?? $meeting->title,
            'description' => array_key_exists('description', $data) ? $data['description'] : $meeting->description,
            'start_at' => $data['start_at'] ?? $meeting->start_at,
            'end_at' => array_key_exists('end_at', $data) ? $data['end_at'] : $meeting->end_at,
            'timezone' => $data['timezone'] ?? $meeting->timezone,
            'include_customer' => array_key_exists('include_customer', $data)
                ? (bool) $data['include_customer']
                : $meeting->include_customer,
        ])->save();

        if (isset($data['participant_ids']) && is_array($data['participant_ids'])) {
            $ids = $this->normalizeParticipantIds($data['participant_ids']);
            $sync = [$meeting->created_by => ['role' => 'host']];
            foreach ($ids as $userId) {
                $sync[(int) $userId] = ['role' => ((int) $userId === (int) $meeting->created_by) ? 'host' : 'attendee'];
            }
            $meeting->participants()->sync($sync);
        }

        return $meeting->fresh(['participants:id,name,email,role', 'creator:id,name,email', 'task:id,title', 'project:id,title', 'supplier:id,name,display_name']) ?? $meeting;
    }

    public function cancel(User $actor, Meeting $meeting): Meeting
    {
        $this->assertCanManageMeeting($actor, $meeting);

        $provider = $meeting->provider instanceof MeetingProvider
            ? $meeting->provider
            : MeetingProvider::from((string) $meeting->provider);

        if ($provider->createsRemoteSession() && filled($meeting->meeting_id)) {
            $driver = $this->providers->driver($provider);
            if ($provider === MeetingProvider::GoogleMeet && $driver instanceof GoogleMeetProvider) {
                $driver->cancelForHost($actor, (string) $meeting->meeting_id, $meeting->external_event_id);
            } else {
                $driver->cancel((string) $meeting->meeting_id, $meeting->external_event_id);
            }
        }

        $meeting->update(['status' => MeetingStatus::Cancelled]);

        return $meeting->fresh(['participants:id,name,email,role', 'creator:id,name,email']) ?? $meeting;
    }

    /**
     * @return Collection<int, Meeting>
     */
    public function listForActor(User $actor, array $filters = []): Collection
    {
        $query = Meeting::query()
            ->with(['creator:id,name', 'task:id,title', 'project:id,title', 'supplier:id,name,display_name'])
            ->latest('start_at');

        $this->scopeVisibleTo($query, $actor);

        if (isset($filters['task_id'])) {
            $query->where('task_id', (int) $filters['task_id']);
        }
        if (isset($filters['project_id'])) {
            $query->where('project_id', (int) $filters['project_id']);
        }
        if (isset($filters['status']) && in_array($filters['status'], MeetingStatus::values(), true)) {
            $query->where('status', $filters['status']);
        }

        return $query->limit(100)->get();
    }

    /**
     * Public/safe payload — host_url only for managers/hosts.
     *
     * @return array<string, mixed>
     */
    public function serialize(Meeting $meeting, User $viewer): array
    {
        $canManage = $this->canManageMeeting($viewer, $meeting);
        $includeJoin = $canManage
            || $meeting->participants->contains('id', $viewer->id)
            || ($viewer->role === UserRole::Supplier && $this->supplierCanSee($viewer, $meeting))
            || ($viewer->role === UserRole::Customer && $meeting->include_customer && (int) $meeting->customer_id === (int) $viewer->id);

        $provider = $meeting->provider instanceof MeetingProvider
            ? $meeting->provider
            : MeetingProvider::from((string) $meeting->provider);
        $status = $meeting->status instanceof MeetingStatus
            ? $meeting->status
            : MeetingStatus::from((string) $meeting->status);

        return [
            'id' => $meeting->id,
            'provider' => $provider->value,
            'provider_label_ar' => $provider->labelAr(),
            'meeting_id' => $meeting->meeting_id,
            'join_url' => $includeJoin ? $meeting->join_url : null,
            'host_url' => $canManage ? $meeting->host_url : null,
            'start_at' => $meeting->start_at?->toIso8601String(),
            'end_at' => $meeting->end_at?->toIso8601String(),
            'timezone' => $meeting->timezone,
            'title' => $meeting->title,
            'description' => $meeting->description,
            'status' => $status->value,
            'status_label_ar' => $status->labelAr(),
            'external_event_id' => $canManage ? $meeting->external_event_id : null,
            'calendar_event_url' => $canManage ? $meeting->calendarEventUrl() : null,
            'task_id' => $meeting->task_id,
            'project_id' => $meeting->project_id,
            'commercial_quotation_id' => $meeting->commercial_quotation_id,
            'supplier_id' => $canManage ? $meeting->supplier_id : null,
            'customer_id' => $canManage ? $meeting->customer_id : null,
            'include_customer' => (bool) $meeting->include_customer,
            'can_manage' => $canManage,
            'creator' => $meeting->creator ? [
                'id' => $meeting->creator->id,
                'name' => $meeting->creator->name,
            ] : null,
            'participants' => $canManage
                ? $meeting->participants->map(fn (User $user): array => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->pivot->role ?? 'attendee',
                ])->values()->all()
                : [],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function assertCanManage(User $actor, array $data): void
    {
        if (! $this->isStaff($actor)) {
            abort(403);
        }

        if (isset($data['task_id'])) {
            $task = Task::query()->with('project')->findOrFail((int) $data['task_id']);
            if (! $this->canAccessTask($actor, $task)) {
                abort(403);
            }
        }

        if (isset($data['project_id'])) {
            $project = Project::query()->findOrFail((int) $data['project_id']);
            if (! $this->canAccessProject($actor, $project)) {
                abort(403);
            }
        }

        if (isset($data['commercial_quotation_id'])) {
            CommercialQuotation::query()->findOrFail((int) $data['commercial_quotation_id']);
            if (! ($actor->role instanceof UserRole) || ! $actor->role->canManageQuoteRequests()) {
                abort(403);
            }
        }

        if (isset($data['supplier_id'])) {
            Supplier::query()->findOrFail((int) $data['supplier_id']);
        }
    }

    public function assertCanManageMeeting(User $actor, Meeting $meeting): void
    {
        if (! $this->canManageMeeting($actor, $meeting)) {
            abort(403);
        }
    }

    public function assertCanViewMeeting(User $actor, Meeting $meeting): void
    {
        if ($this->canManageMeeting($actor, $meeting)) {
            return;
        }
        if ($meeting->participants()->where('users.id', $actor->id)->exists()) {
            return;
        }
        if ($actor->role === UserRole::Customer && $meeting->include_customer && (int) $meeting->customer_id === (int) $actor->id) {
            return;
        }
        if ($actor->role === UserRole::Supplier && $this->supplierCanSee($actor, $meeting)) {
            return;
        }

        abort(404);
    }

    public function canManageMeeting(User $actor, Meeting $meeting): bool
    {
        if (! $this->isStaff($actor)) {
            return false;
        }
        if ((int) $meeting->created_by === (int) $actor->id) {
            return true;
        }
        if ($actor->role === UserRole::Owner || $actor->role === UserRole::AdminManager) {
            return true;
        }
        if ($meeting->task_id) {
            $meeting->loadMissing('task.project');

            return $meeting->task !== null && $this->canAccessTask($actor, $meeting->task);
        }
        if ($meeting->project_id) {
            $meeting->loadMissing('project');

            return $meeting->project !== null && $this->canAccessProject($actor, $meeting->project);
        }

        return false;
    }

    private function scopeVisibleTo(Builder $query, User $actor): void
    {
        if ($actor->role === UserRole::Owner || $actor->role === UserRole::AdminManager) {
            return;
        }

        if ($actor->role === UserRole::Customer) {
            $query->where('customer_id', $actor->id)->where('include_customer', true);

            return;
        }

        if ($actor->role === UserRole::Supplier) {
            $supplierId = $actor->supplierProfile?->id;
            $query->where(function (Builder $inner) use ($actor, $supplierId): void {
                $inner->whereHas('participants', fn ($p) => $p->where('users.id', $actor->id));
                if ($supplierId) {
                    $inner->orWhere('supplier_id', $supplierId)
                        ->orWhereHas('task', fn ($t) => $t->where('supplier_id', $supplierId));
                }
            });

            return;
        }

        $query->where(function (Builder $inner) use ($actor): void {
            $inner->where('created_by', $actor->id)
                ->orWhereHas('participants', fn ($p) => $p->where('users.id', $actor->id))
                ->orWhereHas('task', function ($t) use ($actor): void {
                    $t->where('assigned_to', $actor->id)
                        ->orWhere('created_by', $actor->id)
                        ->orWhereHas('project', fn ($p) => $p->where('account_manager_id', $actor->id));
                })
                ->orWhereHas('project', fn ($p) => $p->where('account_manager_id', $actor->id));
        });
    }

    private function supplierCanSee(User $actor, Meeting $meeting): bool
    {
        $supplierId = $actor->supplierProfile?->id;
        if (! $supplierId) {
            return false;
        }

        if ((int) $meeting->supplier_id === (int) $supplierId) {
            return true;
        }

        $meeting->loadMissing('task');

        return $meeting->task !== null && (int) $meeting->task->supplier_id === (int) $supplierId;
    }

    private function isStaff(User $actor): bool
    {
        return $actor->role instanceof UserRole && (
            $actor->role->usesEmployeeWorkspace()
            || $actor->role === UserRole::Owner
            || $actor->role === UserRole::AdminManager
        );
    }

    private function canAccessTask(User $actor, Task $task): bool
    {
        if ((int) $task->created_by === (int) $actor->id || (int) $task->assigned_to === (int) $actor->id) {
            return true;
        }
        if ($actor->role === UserRole::Owner || $actor->role === UserRole::AdminManager) {
            return true;
        }
        $task->loadMissing('project');

        return $task->project !== null && (int) $task->project->account_manager_id === (int) $actor->id;
    }

    private function canAccessProject(User $actor, Project $project): bool
    {
        if ($actor->role === UserRole::Owner || $actor->role === UserRole::AdminManager) {
            return true;
        }

        return (int) $project->account_manager_id === (int) $actor->id;
    }

    /**
     * @param  list<mixed>  $ids
     * @return list<int>
     */
    private function normalizeParticipantIds(array $ids): array
    {
        return collect($ids)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $participantIds
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    private function resolveAttendeeEmails(array $participantIds, array $data): array
    {
        $emails = User::query()->whereIn('id', $participantIds)->pluck('email')->filter()->values()->all();

        if (! empty($data['include_customer']) && ! empty($data['customer_id'])) {
            $customerEmail = User::query()->whereKey((int) $data['customer_id'])->value('email');
            if (is_string($customerEmail) && $customerEmail !== '') {
                $emails[] = $customerEmail;
            }
        }

        return array_values(array_unique($emails));
    }
}
