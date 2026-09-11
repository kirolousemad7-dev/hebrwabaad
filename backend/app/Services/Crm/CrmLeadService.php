<?php

namespace App\Services\Crm;

use App\Enums\CrmActivityType;
use App\Enums\CrmLeadPriority;
use App\Enums\CrmLeadStatus;
use App\Enums\UserRole;
use App\Models\ConsultationLead;
use App\Models\ContactInquiry;
use App\Models\CrmActivity;
use App\Models\CrmAuditLog;
use App\Models\CrmLead;
use App\Models\CrmLeadSource;
use App\Models\CrmLostReason;
use App\Models\CrmPipelineStage;
use App\Models\Order;
use App\Models\Project;
use App\Models\User;
use App\Services\OrderService;
use App\Services\PlatformNotifier;
use App\Services\ProjectService;
use App\Services\Workflow\WorkflowAutomationEngine;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CrmLeadService
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly ProjectService $projects,
        private readonly CrmAssignmentService $assignment,
        private readonly PlatformNotifier $notifier,
    ) {}

    /**
     * @return list<string>
     */
    public function eagerLoad(): array
    {
        return [
            'stage',
            'source',
            'assignee:id,name,email,role',
            'creator:id,name,email',
            'lostReason',
            'service:id,name,slug',
            'package:id,name,slug',
            'customer:id,name,email',
            'company:id,name,status',
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, CrmLead>
     */
    public function paginateFor(User $actor, array $filters = []): LengthAwarePaginator
    {
        $query = CrmLead::query()->with($this->eagerLoad());
        $this->scopeVisibleTo($query, $actor);
        $this->applyFilters($query, $filters);

        return $query->paginate($this->perPage($filters));
    }

    public function load(CrmLead $lead): CrmLead
    {
        return $lead->load($this->eagerLoad());
    }

    public function assertVisible(User $actor, CrmLead $lead): CrmLead
    {
        if ($this->canManageAll($actor)) {
            return $this->load($lead);
        }

        if ($actor->role === UserRole::SalesRepresentative && (int) $lead->assigned_to === (int) $actor->id) {
            return $this->load($lead);
        }

        throw new AuthorizationException('You do not have access to this lead.');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createLead(User $actor, array $data): CrmLead
    {
        $lead = DB::transaction(function () use ($actor, $data): CrmLead {
            $stage = $this->resolveStage($data['stage_id'] ?? null);
            $assignedTo = $this->resolveAssigneeId($actor, $data['assigned_to'] ?? null);

            $lead = CrmLead::query()->create([
                'reference' => $this->generateReference(),
                'full_name' => $data['full_name'],
                'company_name' => $data['company_name'] ?? null,
                'job_title' => $data['job_title'] ?? null,
                'phone' => $data['phone'] ?? null,
                'alt_phone' => $data['alt_phone'] ?? null,
                'whatsapp' => $data['whatsapp'] ?? null,
                'email' => $data['email'] ?? null,
                'country' => $data['country'] ?? null,
                'city' => $data['city'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'service_id' => $data['service_id'] ?? null,
                'package_id' => $data['package_id'] ?? null,
                'estimated_budget' => $data['estimated_budget'] ?? null,
                'deal_value' => $data['deal_value'] ?? null,
                'status' => $data['status'] ?? CrmLeadStatus::New->value,
                'stage_id' => $stage->id,
                'priority' => $data['priority'] ?? CrmLeadPriority::Medium->value,
                'assigned_to' => $assignedTo,
                'next_follow_up_at' => $data['next_follow_up_at'] ?? null,
                'expected_close_at' => $data['expected_close_at'] ?? null,
                'score' => $data['score'] ?? 0,
                'tags' => $data['tags'] ?? null,
                'notes' => $data['notes'] ?? null,
                'company_id' => $data['company_id'] ?? null,
                'created_by' => $actor->id,
            ]);

            $this->audit($actor, $lead, 'created', null, $lead->toArray());

            return $this->load($lead);
        });

        $lead = $this->assignment->assignAfterCreate($lead);

        try {
            app(WorkflowAutomationEngine::class)->dispatch('crm.lead.created', [
                'source_type' => 'crm_lead',
                'source_id' => $lead->id,
                'actor_id' => $actor->id,
                'title' => 'عميل محتمل جديد: '.$lead->full_name,
                'related_type' => 'crm_lead',
                'related_id' => $lead->id,
                'assignee_ids' => array_filter([$lead->assigned_to]),
                'payload' => [
                    'lead_id' => $lead->id,
                    'reference' => $lead->reference,
                    'status' => $lead->status instanceof CrmLeadStatus
                        ? $lead->status->value
                        : (string) $lead->status,
                ],
            ]);
        } catch (\Throwable) {
            // Workflow hooks must never break lead creation.
        }

        return $this->load($lead);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateLead(User $actor, CrmLead $lead, array $data): CrmLead
    {
        $this->assertVisible($actor, $lead);

        return DB::transaction(function () use ($actor, $lead, $data): CrmLead {
            $old = $lead->toArray();

            if (array_key_exists('stage_id', $data) && $data['stage_id'] !== null) {
                $data['stage_id'] = $this->resolveStage($data['stage_id'])->id;
            }

            if (array_key_exists('assigned_to', $data)) {
                $data['assigned_to'] = $this->resolveAssigneeId($actor, $data['assigned_to']);
            }

            $lead->fill(collect($data)->only([
                'full_name',
                'company_name',
                'job_title',
                'phone',
                'alt_phone',
                'whatsapp',
                'email',
                'country',
                'city',
                'source_id',
                'service_id',
                'package_id',
                'estimated_budget',
                'deal_value',
                'status',
                'stage_id',
                'priority',
                'assigned_to',
                'next_follow_up_at',
                'expected_close_at',
                'score',
                'tags',
                'notes',
                'company_id',
            ])->all());

            $lead->save();
            $this->audit($actor, $lead, 'updated', $old, $lead->fresh()?->toArray());

            return $this->load($lead->fresh() ?? $lead);
        });
    }

    public function assign(User $actor, CrmLead $lead, int $salesRepId): CrmLead
    {
        if (! $actor->role instanceof UserRole || ! $actor->role->canManageCrmTeam()) {
            throw ValidationException::withMessages([
                'assigned_to' => ['You cannot assign CRM leads.'],
            ]);
        }

        $rep = User::query()->find($salesRepId);

        if ($rep === null || ! $rep->is_active || ! $rep->role instanceof UserRole || ! $rep->role->isSalesRole()) {
            throw ValidationException::withMessages([
                'assigned_to' => ['Selected sales representative is not valid.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $lead, $rep): CrmLead {
            $oldAssignee = $lead->assigned_to;
            $lead->update(['assigned_to' => $rep->id]);

            CrmActivity::query()->create([
                'lead_id' => $lead->id,
                'user_id' => $actor->id,
                'type' => CrmActivityType::Assignment,
                'occurred_at' => now(),
                'result' => 'Assigned to '.$rep->name,
                'notes' => null,
            ]);

            $this->audit($actor, $lead, 'assigned', ['assigned_to' => $oldAssignee], ['assigned_to' => $rep->id]);

            return $this->load($lead->fresh() ?? $lead);
        });
    }

    public function moveStage(User $actor, CrmLead $lead, int $stageId): CrmLead
    {
        $this->assertVisible($actor, $lead);
        $stage = $this->resolveStage($stageId);

        return DB::transaction(function () use ($actor, $lead, $stage): CrmLead {
            $from = $lead->stage_id;
            $attributes = ['stage_id' => $stage->id];

            if ($stage->is_won) {
                $attributes['status'] = CrmLeadStatus::Won->value;
            } elseif ($stage->is_lost) {
                $attributes['status'] = CrmLeadStatus::Lost->value;
            }

            $lead->update($attributes);

            CrmActivity::query()->create([
                'lead_id' => $lead->id,
                'user_id' => $actor->id,
                'type' => CrmActivityType::StageChange,
                'occurred_at' => now(),
                'result' => 'Moved to '.$stage->name,
                'notes' => null,
            ]);

            $this->audit($actor, $lead, 'stage_changed', ['stage_id' => $from], ['stage_id' => $stage->id]);

            return $this->load($lead->fresh() ?? $lead);
        });
    }

    public function createFromContactInquiry(ContactInquiry $inquiry): CrmLead
    {
        $createdNew = false;

        $lead = DB::transaction(function () use ($inquiry, &$createdNew): CrmLead {
            if ($inquiry->crm_lead_id !== null) {
                $existing = CrmLead::query()->find($inquiry->crm_lead_id);
                if ($existing !== null) {
                    return $this->load($existing);
                }
            }

            $source = CrmLeadSource::query()->where('slug', 'website-contact')->first()
                ?? CrmLeadSource::query()->orderBy('sort_order')->first();
            $stage = CrmPipelineStage::query()->where('slug', 'new-lead')->first()
                ?? CrmPipelineStage::query()->orderBy('sort_order')->firstOrFail();

            $lead = CrmLead::query()->create([
                'reference' => $this->generateReference(),
                'full_name' => $inquiry->name,
                'phone' => $inquiry->phone,
                'email' => $inquiry->email,
                'notes' => $inquiry->message,
                'status' => CrmLeadStatus::New->value,
                'stage_id' => $stage->id,
                'priority' => CrmLeadPriority::Medium->value,
                'source_id' => $source?->id,
                'contact_inquiry_id' => $inquiry->id,
                'score' => 0,
            ]);

            $inquiry->update(['crm_lead_id' => $lead->id]);
            $createdNew = true;

            $this->audit(null, $lead, 'created_from_contact_inquiry', null, [
                'contact_inquiry_id' => $inquiry->id,
            ]);

            return $this->load($lead);
        });

        if ($createdNew) {
            $lead = $this->assignment->assignAfterCreate($lead);
        }

        return $this->load($lead);
    }

    public function createFromConsultationLead(ConsultationLead $consultationLead): CrmLead
    {
        return DB::transaction(function () use ($consultationLead): CrmLead {
            $existing = CrmLead::query()
                ->where('consultation_lead_id', $consultationLead->id)
                ->first();

            if ($existing !== null) {
                return $this->load($existing);
            }

            $source = CrmLeadSource::query()->where('slug', 'ai-consultant')->first()
                ?? CrmLeadSource::query()->orderBy('sort_order')->first();
            $stage = CrmPipelineStage::query()->where('slug', 'new-lead')->first()
                ?? CrmPipelineStage::query()->orderBy('sort_order')->firstOrFail();

            $lead = CrmLead::query()->create([
                'reference' => $this->generateReference(),
                'full_name' => $consultationLead->name,
                'company_name' => $consultationLead->business_name,
                'phone' => $consultationLead->phone,
                'email' => $consultationLead->email,
                'notes' => 'Contact method: '.($consultationLead->contact_method ?? 'email'),
                'status' => CrmLeadStatus::New->value,
                'stage_id' => $stage->id,
                'priority' => CrmLeadPriority::Medium->value,
                'source_id' => $source?->id,
                'consultation_lead_id' => $consultationLead->id,
                'score' => 0,
            ]);

            $this->audit(null, $lead, 'created_from_consultation', null, [
                'consultation_lead_id' => $consultationLead->id,
            ]);

            return $this->load($lead);
        });
    }

    /**
     * @return Collection<int, CrmLead>
     */
    public function findDuplicates(?string $phone, ?string $email, ?string $whatsapp, ?int $exceptId = null): Collection
    {
        $rawPhone = is_string($phone) ? trim($phone) : null;
        $rawEmail = is_string($email) ? strtolower(trim($email)) : null;
        $rawWhatsapp = is_string($whatsapp) ? trim($whatsapp) : null;
        $phoneDigits = $this->normalizeContact($rawPhone);
        $whatsappDigits = $this->normalizeContact($rawWhatsapp);

        if (($rawPhone === null || $rawPhone === '')
            && ($rawEmail === null || $rawEmail === '')
            && ($rawWhatsapp === null || $rawWhatsapp === '')) {
            return new Collection;
        }

        $query = CrmLead::query()->with($this->eagerLoad());

        $query->where(function (Builder $outer) use ($rawPhone, $rawEmail, $rawWhatsapp, $phoneDigits, $whatsappDigits): void {
            if ($rawPhone !== null && $rawPhone !== '') {
                $outer->orWhere('phone', $rawPhone)
                    ->orWhere('alt_phone', $rawPhone)
                    ->orWhere('whatsapp', $rawPhone);
            }
            if ($phoneDigits !== null) {
                $outer->orWhere('phone', $phoneDigits)
                    ->orWhere('alt_phone', $phoneDigits)
                    ->orWhere('whatsapp', $phoneDigits);
            }
            if ($rawWhatsapp !== null && $rawWhatsapp !== '') {
                $outer->orWhere('whatsapp', $rawWhatsapp)
                    ->orWhere('phone', $rawWhatsapp);
            }
            if ($whatsappDigits !== null) {
                $outer->orWhere('whatsapp', $whatsappDigits)
                    ->orWhere('phone', $whatsappDigits);
            }
            if ($rawEmail !== null && $rawEmail !== '') {
                $outer->orWhereRaw('LOWER(email) = ?', [$rawEmail]);
            }
        });

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->latest()->limit(25)->get();
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{lead: CrmLead, customer: User, order: Order, project: ?Project}
     */
    public function convertWon(User $actor, CrmLead $lead, array $options = []): array
    {
        $this->assertVisible($actor, $lead);

        if ($lead->status === CrmLeadStatus::Won && $lead->won_order_id !== null) {
            throw ValidationException::withMessages([
                'lead' => ['This lead has already been converted.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $lead, $options): array {
            $customer = $this->resolveOrCreateCustomer($lead, $options);
            $manager = $this->resolveAccountManager();

            $order = $this->orders->create($manager, [
                'title' => $options['order_title'] ?? ('CRM Deal — '.$lead->full_name),
                'description' => $options['order_description'] ?? $lead->notes,
                'customer_id' => $customer->id,
                'service_id' => $options['service_id'] ?? $lead->service_id,
                'package_id' => $options['package_id'] ?? $lead->package_id,
                'project_id' => null,
            ]);

            $project = null;
            if (($options['create_project'] ?? true) === true) {
                $project = $this->projects->create($manager, [
                    'title' => $options['project_title'] ?? ('Project — '.$lead->full_name),
                    'description' => $options['project_description'] ?? $lead->notes,
                    'customer_id' => $customer->id,
                ]);

                $order->update(['project_id' => $project->id]);
                $order = $this->orders->load($order->fresh() ?? $order);
            }

            $wonStage = CrmPipelineStage::query()->where('is_won', true)->orderBy('sort_order')->first();

            $lead->update([
                'status' => CrmLeadStatus::Won->value,
                'customer_id' => $customer->id,
                'converted_at' => now(),
                'won_order_id' => $order->id,
                'won_project_id' => $project?->id,
                'stage_id' => $wonStage?->id ?? $lead->stage_id,
                'deal_value' => $options['deal_value'] ?? $lead->deal_value,
            ]);

            CrmActivity::query()->create([
                'lead_id' => $lead->id,
                'user_id' => $actor->id,
                'type' => CrmActivityType::Conversion,
                'occurred_at' => now(),
                'result' => 'Converted to customer #'.$customer->id.' / order '.$order->reference,
            ]);

            $this->audit($actor, $lead, 'converted', null, [
                'customer_id' => $customer->id,
                'order_id' => $order->id,
                'project_id' => $project?->id,
            ]);

            $loaded = $this->load($lead->fresh() ?? $lead);
            $this->notifier->crmDealWon($loaded);

            try {
                app(WorkflowAutomationEngine::class)->dispatch('crm.opportunity.won', [
                    'source_type' => 'crm_lead',
                    'source_id' => $loaded->id,
                    'actor_id' => $actor->id,
                    'title' => 'صفقة رابحة: '.$loaded->full_name,
                    'related_type' => 'crm_lead',
                    'related_id' => $loaded->id,
                    'assignee_ids' => [$manager->id],
                    'payload' => [
                        'lead_id' => $loaded->id,
                        'order_id' => $order->id,
                        'project_id' => $project?->id,
                    ],
                ]);
            } catch (\Throwable) {
                // Workflow hooks must never break convertWon.
            }

            return [
                'lead' => $loaded,
                'customer' => $customer,
                'order' => $order,
                'project' => $project,
            ];
        });
    }

    public function markLost(
        User $actor,
        CrmLead $lead,
        int $reasonId,
        ?string $notes = null,
        ?string $competitor = null,
    ): CrmLead {
        $this->assertVisible($actor, $lead);

        $reason = CrmLostReason::query()->where('is_active', true)->find($reasonId);

        if ($reason === null) {
            throw ValidationException::withMessages([
                'lost_reason_id' => ['A valid lost reason is required.'],
            ]);
        }

        return DB::transaction(function () use ($actor, $lead, $reason, $notes, $competitor): CrmLead {
            $lostStage = CrmPipelineStage::query()->where('is_lost', true)->orderBy('sort_order')->first();

            $lead->update([
                'status' => CrmLeadStatus::Lost->value,
                'lost_reason_id' => $reason->id,
                'lost_notes' => $notes,
                'competitor_name' => $competitor,
                'stage_id' => $lostStage?->id ?? $lead->stage_id,
            ]);

            CrmActivity::query()->create([
                'lead_id' => $lead->id,
                'user_id' => $actor->id,
                'type' => CrmActivityType::StageChange,
                'occurred_at' => now(),
                'result' => 'Marked lost: '.$reason->name,
                'notes' => $notes,
            ]);

            $this->audit($actor, $lead, 'lost', null, [
                'lost_reason_id' => $reason->id,
                'lost_notes' => $notes,
                'competitor_name' => $competitor,
            ]);

            return $this->load($lead->fresh() ?? $lead);
        });
    }

    public function generateReference(): string
    {
        $year = now()->format('Y');
        $prefix = 'LD-'.$year.'-';

        $latest = CrmLead::query()
            ->where('reference', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('reference')
            ->value('reference');

        $next = 1;
        if (is_string($latest) && preg_match('/(\d+)$/', $latest, $matches) === 1) {
            $next = ((int) $matches[1]) + 1;
        }

        return sprintf('%s%04d', $prefix, $next);
    }

    /**
     * @param  Builder<CrmLead>  $query
     */
    private function scopeVisibleTo(Builder $query, User $actor): void
    {
        if ($this->canManageAll($actor)) {
            return;
        }

        $query->where('assigned_to', $actor->id);
    }

    private function canManageAll(User $actor): bool
    {
        return $actor->role instanceof UserRole && $actor->role->canManageCrmTeam();
    }

    /**
     * @param  Builder<CrmLead>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $search = is_string($filters['q'] ?? null) ? trim($filters['q']) : '';
        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(function (Builder $inner) use ($term): void {
                $inner->where('full_name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('company_name', 'like', $term)
                    ->orWhere('reference', 'like', $term);
            });
        }

        if (is_string($filters['status'] ?? null) && in_array($filters['status'], CrmLeadStatus::values(), true)) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['stage_id']) && $filters['stage_id'] !== '') {
            $query->where('stage_id', (int) $filters['stage_id']);
        }

        $assignedTo = $filters['assigned_to'] ?? null;
        if ($assignedTo === 'unassigned' || ($filters['unassigned'] ?? null) === '1' || ($filters['unassigned'] ?? null) === 1 || ($filters['unassigned'] ?? null) === true) {
            $query->whereNull('assigned_to');
        } elseif (isset($assignedTo) && $assignedTo !== '') {
            $query->where('assigned_to', (int) $assignedTo);
        }

        if (($filters['needs_attention'] ?? null) === '1'
            || ($filters['needs_attention'] ?? null) === 1
            || ($filters['needs_attention'] ?? null) === true
            || ($filters['stale'] ?? null) === '1'
            || ($filters['stale'] ?? null) === 1
            || ($filters['stale'] ?? null) === true) {
            $query->where('needs_attention', true);
        }

        if (is_string($filters['priority'] ?? null) && in_array($filters['priority'], CrmLeadPriority::values(), true)) {
            $query->where('priority', $filters['priority']);
        }

        $query->latest();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function perPage(array $filters): int
    {
        $perPage = (int) ($filters['per_page'] ?? 15);

        return max(1, min($perPage, 50));
    }

    private function resolveStage(mixed $stageId): CrmPipelineStage
    {
        if ($stageId === null || $stageId === '') {
            $stage = CrmPipelineStage::query()
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->first();

            if ($stage === null) {
                throw ValidationException::withMessages([
                    'stage_id' => ['No active pipeline stage is configured.'],
                ]);
            }

            return $stage;
        }

        $stage = CrmPipelineStage::query()->where('is_active', true)->find((int) $stageId);

        if ($stage === null) {
            throw ValidationException::withMessages([
                'stage_id' => ['Selected pipeline stage is not valid.'],
            ]);
        }

        return $stage;
    }

    private function resolveAssigneeId(User $actor, mixed $assigneeId): ?int
    {
        if ($assigneeId === null || $assigneeId === '') {
            return $actor->role === UserRole::SalesRepresentative ? $actor->id : null;
        }

        $user = User::query()->find((int) $assigneeId);

        if ($user === null || ! $user->is_active || ! $user->role instanceof UserRole || ! $user->role->canAccessCrm()) {
            throw ValidationException::withMessages([
                'assigned_to' => ['Selected assignee is not valid.'],
            ]);
        }

        if ($actor->role === UserRole::SalesRepresentative && (int) $user->id !== (int) $actor->id) {
            throw ValidationException::withMessages([
                'assigned_to' => ['Sales representatives can only assign leads to themselves.'],
            ]);
        }

        return $user->id;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function resolveOrCreateCustomer(CrmLead $lead, array $options): User
    {
        if (! empty($options['customer_id'])) {
            $customer = User::query()->find((int) $options['customer_id']);
            if ($customer === null || $customer->role !== UserRole::Customer) {
                throw ValidationException::withMessages([
                    'customer_id' => ['Selected customer is not valid.'],
                ]);
            }

            return $customer;
        }

        if ($lead->customer_id !== null) {
            $existing = User::query()->find($lead->customer_id);
            if ($existing !== null && $existing->role === UserRole::Customer) {
                return $existing;
            }
        }

        $email = $options['customer_email'] ?? $lead->email;
        if (! is_string($email) || trim($email) === '') {
            $email = 'crm-lead-'.$lead->id.'@hebr.local';
        }

        $existingByEmail = User::query()->where('email', $email)->first();
        if ($existingByEmail !== null) {
            if ($existingByEmail->role !== UserRole::Customer) {
                throw ValidationException::withMessages([
                    'email' => ['A non-customer account already uses this email.'],
                ]);
            }

            return $existingByEmail;
        }

        return User::query()->create([
            'name' => $options['customer_name'] ?? $lead->full_name,
            'email' => $email,
            'password' => Str::password(16),
            'role' => UserRole::Customer,
            'is_active' => true,
        ]);
    }

    private function resolveAccountManager(): User
    {
        $manager = User::query()
            ->active()
            ->where('role', UserRole::AccountManager)
            ->orderBy('id')
            ->first();

        if ($manager === null) {
            throw ValidationException::withMessages([
                'account_manager' => ['No active account manager is available to own the order/project.'],
            ]);
        }

        return $manager;
    }

    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    private function audit(?User $actor, CrmLead $lead, string $action, ?array $old, ?array $new): void
    {
        CrmAuditLog::query()->create([
            'user_id' => $actor?->id,
            'auditable_type' => $lead->getMorphClass(),
            'auditable_id' => $lead->id,
            'action' => $action,
            'old_values' => $old,
            'new_values' => $new,
            'created_at' => now(),
        ]);
    }

    private function normalizeContact(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $value);

        return $digits !== null && $digits !== '' ? $digits : null;
    }

    private function normalizeEmail(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $email = strtolower(trim($value));

        return $email !== '' ? $email : null;
    }
}
