<?php

namespace App\Services\NeedsDiscovery;

use App\Enums\CrmLeadPriority;
use App\Enums\CrmLeadStatus;
use App\Enums\CrmActivityType;
use App\Enums\RequirementStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\UserRole;
use App\Models\CrmActivity;
use App\Models\CrmLead;
use App\Models\CrmLeadSource;
use App\Models\CrmPipelineStage;
use App\Models\Requirement;
use App\Models\Service;
use App\Models\Task;
use App\Models\User;
use App\Services\Crm\CrmAssignmentService;
use App\Services\Crm\CrmLeadService;
use App\Services\PlatformNotifier;
use App\Services\Workflow\WorkflowAutomationEngine;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class NeedsDiscoveryService
{
    public const SOURCE = 'needs-discovery';

    public function __construct(
        private readonly CrmLeadService $crmLeads,
        private readonly CrmAssignmentService $assignment,
        private readonly PlatformNotifier $notifier,
    ) {}

    /**
     * Deterministic conversational steps (no AI).
     *
     * @return list<array<string, mixed>>
     */
    public function steps(): array
    {
        $services = Service::query()
            ->active()
            ->public()
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name', 'slug', 'category'])
            ->map(fn (Service $service) => [
                'id' => 'service:'.$service->slug,
                'label' => $service->name,
                'value' => $service->name,
                'service_id' => $service->id,
                'category' => $service->category->value,
            ])
            ->values()
            ->all();

        $services[] = ['id' => 'service:other', 'label' => 'أخرى / لست متأكداً', 'value' => 'أخرى'];

        return [
            [
                'id' => 'need',
                'prompt' => 'ماذا تحتاج؟',
                'type' => 'choice',
                'quick_replies' => [
                    ['id' => 'need:website', 'label' => 'موقع أو متجر', 'value' => 'موقع أو متجر'],
                    ['id' => 'need:branding', 'label' => 'هوية وتصميم', 'value' => 'هوية وتصميم'],
                    ['id' => 'need:marketing', 'label' => 'تسويق وحملات', 'value' => 'تسويق وحملات'],
                    ['id' => 'need:printing', 'label' => 'طباعة وتغليف', 'value' => 'طباعة وتغليف'],
                    ['id' => 'need:events', 'label' => 'فعاليات', 'value' => 'فعاليات'],
                    ['id' => 'need:other', 'label' => 'شيء آخر', 'value' => 'شيء آخر'],
                ],
            ],
            [
                'id' => 'project_type',
                'prompt' => 'ما نوع المشروع؟',
                'type' => 'choice',
                'quick_replies' => [
                    ['id' => 'project:new', 'label' => 'مشروع جديد', 'value' => 'مشروع جديد'],
                    ['id' => 'project:upgrade', 'label' => 'تطوير مشروع قائم', 'value' => 'تطوير مشروع قائم'],
                    ['id' => 'project:campaign', 'label' => 'حملة مؤقتة', 'value' => 'حملة مؤقتة'],
                    ['id' => 'project:ongoing', 'label' => 'خدمة مستمرة', 'value' => 'خدمة مستمرة'],
                ],
            ],
            [
                'id' => 'service',
                'prompt' => 'ما الخدمة؟',
                'type' => 'choice',
                'quick_replies' => $services,
            ],
            [
                'id' => 'budget',
                'prompt' => 'الميزانية التقريبية؟',
                'type' => 'choice',
                'quick_replies' => [
                    ['id' => 'budget:lt5', 'label' => 'أقل من 5,000 ر.س', 'value' => 'أقل من 5,000 ر.س'],
                    ['id' => 'budget:5-15', 'label' => '5,000 – 15,000 ر.س', 'value' => '5,000 – 15,000 ر.س'],
                    ['id' => 'budget:15-50', 'label' => '15,000 – 50,000 ر.س', 'value' => '15,000 – 50,000 ر.س'],
                    ['id' => 'budget:gt50', 'label' => 'أكثر من 50,000 ر.س', 'value' => 'أكثر من 50,000 ر.س'],
                    ['id' => 'budget:unknown', 'label' => 'غير محدد بعد', 'value' => 'غير محدد بعد'],
                ],
            ],
            [
                'id' => 'deadline',
                'prompt' => 'الموعد المتوقع؟',
                'type' => 'choice',
                'quick_replies' => [
                    ['id' => 'deadline:asap', 'label' => 'في أقرب وقت', 'value' => 'في أقرب وقت'],
                    ['id' => 'deadline:2w', 'label' => 'خلال أسبوعين', 'value' => 'خلال أسبوعين'],
                    ['id' => 'deadline:1m', 'label' => 'خلال شهر', 'value' => 'خلال شهر'],
                    ['id' => 'deadline:3m', 'label' => 'خلال 3 أشهر', 'value' => 'خلال 3 أشهر'],
                    ['id' => 'deadline:flexible', 'label' => 'مرن', 'value' => 'مرن'],
                ],
            ],
            [
                'id' => 'has_files',
                'prompt' => 'هل لديك ملفات؟',
                'type' => 'choice',
                'quick_replies' => [
                    ['id' => 'files:yes', 'label' => 'نعم، سأرفع ملفات', 'value' => 'yes'],
                    ['id' => 'files:no', 'label' => 'لا حالياً', 'value' => 'no'],
                ],
            ],
            [
                'id' => 'contact',
                'prompt' => 'كيف يمكن التواصل معك؟',
                'type' => 'contact',
                'fields' => ['name', 'phone', 'email', 'company'],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<UploadedFile>  $files
     * @return array{requirement: Requirement, lead: CrmLead, created_lead: bool}
     */
    public function submit(array $payload, array $files = []): array
    {
        $answers = is_array($payload['answers'] ?? null) ? $payload['answers'] : [];
        $name = trim((string) ($payload['name'] ?? $answers['contact']['name'] ?? ''));
        $phone = $this->nullableString($payload['phone'] ?? $answers['contact']['phone'] ?? null);
        $email = $this->nullableString($payload['email'] ?? $answers['contact']['email'] ?? null);
        $company = $this->nullableString($payload['company'] ?? $answers['contact']['company'] ?? null);

        $serviceLabel = $this->answerLabel($answers, 'service');
        $category = $this->answerLabel($answers, 'need') ?? $this->answerLabel($answers, 'project_type');
        $budget = $this->answerLabel($answers, 'budget');
        $deadline = $this->answerLabel($answers, 'deadline');
        $description = $this->nullableString($payload['description'] ?? null)
            ?? $this->buildDescription($answers);

        $serviceId = $this->resolveServiceId($answers);
        $recommended = $this->recommendServices($answers, $serviceId);
        $summary = $this->buildSummary($name, $serviceLabel, $budget, $deadline);
        $attachments = $this->storeAttachments($files);

        $result = DB::transaction(function () use (
            $name,
            $phone,
            $email,
            $company,
            $serviceLabel,
            $category,
            $budget,
            $deadline,
            $description,
            $answers,
            $summary,
            $recommended,
            $attachments,
            $serviceId,
        ): array {
            [$lead, $createdLead] = $this->upsertCrmLead([
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'company' => $company,
                'service_id' => $serviceId,
                'budget' => $budget,
                'deadline' => $deadline,
                'summary' => $summary,
                'description' => $description,
                'answers' => $answers,
            ]);

            $requirement = Requirement::query()->create([
                'reference' => $this->generateReference(),
                'name' => $name,
                'phone' => $phone,
                'email' => $email,
                'company' => $company,
                'service' => $serviceLabel,
                'category' => $category,
                'budget' => $budget,
                'deadline' => $deadline,
                'description' => $description,
                'attachments' => $attachments,
                'source' => self::SOURCE,
                'answers' => $answers,
                'summary' => $summary,
                'recommended_services' => $recommended,
                'status' => RequirementStatus::New,
                'assigned_to' => $lead->assigned_to,
                'crm_lead_id' => $lead->id,
                'service_id' => $serviceId,
            ]);

            return [
                'requirement' => $requirement->load(['assignee', 'crmLead', 'catalogService', 'task']),
                'lead' => $lead,
                'created_lead' => $createdLead,
            ];
        });

        $lead = $result['lead'];
        if ($lead->assigned_to !== null) {
            $assignee = User::query()->find($lead->assigned_to);
            if ($assignee !== null) {
                $this->notifier->crmLeadAssigned($lead, $assignee);
            }
        } else {
            $this->notifyOwnersOfRequirement($result['requirement']);
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Requirement>
     */
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        $query = Requirement::query()
            ->with(['assignee:id,name,email', 'crmLead:id,reference,full_name,status', 'catalogService:id,name,slug', 'task:id,title,status'])
            ->latest();

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['q'])) {
            $term = '%'.trim((string) $filters['q']).'%';
            $query->where(function ($inner) use ($term): void {
                $inner->where('name', 'like', $term)
                    ->orWhere('email', 'like', $term)
                    ->orWhere('phone', 'like', $term)
                    ->orWhere('company', 'like', $term)
                    ->orWhere('reference', 'like', $term)
                    ->orWhere('summary', 'like', $term);
            });
        }

        return $query->paginate(max(1, min((int) ($filters['per_page'] ?? 20), 50)));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Requirement $requirement, array $data, User $actor): Requirement
    {
        if (array_key_exists('status', $data) && $data['status'] !== null) {
            $requirement->status = RequirementStatus::from((string) $data['status']);
        }

        foreach (['notes', 'assigned_to', 'summary', 'description'] as $field) {
            if (array_key_exists($field, $data)) {
                $requirement->{$field} = $data[$field];
            }
        }

        if (! empty($data['qualify'])) {
            $requirement = $this->qualify($requirement, $actor);
        } else {
            $requirement->save();
        }

        return $requirement->fresh(['assignee', 'crmLead', 'catalogService', 'task']) ?? $requirement;
    }

    public function qualify(Requirement $requirement, User $actor): Requirement
    {
        return DB::transaction(function () use ($requirement, $actor): Requirement {
            $assigneeId = $requirement->assigned_to
                ?? User::query()->where('role', UserRole::AccountManager->value)->where('is_active', true)->value('id')
                ?? $actor->id;

            if ($requirement->task_id === null) {
                $task = Task::query()->create([
                    'title' => 'متابعة احتياج: '.($requirement->summary ?: $requirement->name),
                    'description' => trim(($requirement->description ?? '')."\n\nالمرجع: ".$requirement->reference),
                    'assigned_to' => $assigneeId,
                    'created_by' => $actor->id,
                    'priority' => TaskPriority::High,
                    'status' => TaskStatus::Todo,
                    'deadline' => now()->addDays(3)->toDateString(),
                ]);
                $requirement->task_id = $task->id;
            }

            $requirement->status = RequirementStatus::Qualified;
            $requirement->qualified = true;
            $requirement->qualified_at = now();
            $requirement->assigned_to = $assigneeId;
            $requirement->save();

            if ($requirement->crm_lead_id !== null) {
                $lead = CrmLead::query()->find($requirement->crm_lead_id);
                if ($lead !== null) {
                    $qualifiedStage = CrmPipelineStage::query()->where('slug', 'qualified')->first();
                    $lead->fill([
                        'assigned_to' => $assigneeId,
                        'priority' => CrmLeadPriority::High->value,
                        'needs_attention' => true,
                    ]);
                    if ($qualifiedStage !== null) {
                        $lead->stage_id = $qualifiedStage->id;
                    }
                    $lead->save();

                    $assignee = User::query()->find($assigneeId);
                    if ($assignee !== null) {
                        $this->notifier->crmLeadAssigned($lead, $assignee);
                    }
                }
            }

            return $requirement->fresh(['assignee', 'crmLead', 'catalogService', 'task']) ?? $requirement;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: CrmLead, 1: bool}
     */
    private function upsertCrmLead(array $data): array
    {
        $duplicates = $this->crmLeads->findDuplicates($data['phone'] ?? null, $data['email'] ?? null, $data['phone'] ?? null);
        $existing = $duplicates->first();

        $source = CrmLeadSource::query()->where('slug', self::SOURCE)->first()
            ?? CrmLeadSource::query()->where('slug', 'website-contact')->first()
            ?? CrmLeadSource::query()->orderBy('sort_order')->first();
        $stage = CrmPipelineStage::query()->where('slug', 'needs-analysis')->first()
            ?? CrmPipelineStage::query()->where('slug', 'new-lead')->first()
            ?? CrmPipelineStage::query()->orderBy('sort_order')->firstOrFail();

        $noteBlock = $this->formatLeadNotes($data);

        if ($existing instanceof CrmLead) {
            $existing->fill([
                'full_name' => $existing->full_name ?: $data['name'],
                'phone' => $existing->phone ?: ($data['phone'] ?? null),
                'email' => $existing->email ?: ($data['email'] ?? null),
                'company_name' => $existing->company_name ?: ($data['company'] ?? null),
                'service_id' => $existing->service_id ?: ($data['service_id'] ?? null),
                'estimated_budget' => $existing->estimated_budget ?: $this->parseBudgetAmount($data['budget'] ?? null),
                'notes' => trim(($existing->notes ? $existing->notes."\n\n" : '').$noteBlock),
                'needs_attention' => true,
                'score' => max((int) $existing->score, 35),
            ]);

            if ($existing->status !== CrmLeadStatus::Won && $existing->status !== CrmLeadStatus::Lost) {
                $existing->stage_id = $stage->id;
            }

            $existing->save();

            $activityUserId = $existing->assigned_to
                ?? User::query()->where('role', UserRole::Owner->value)->value('id')
                ?? User::query()->orderBy('id')->value('id');

            if ($activityUserId !== null) {
                CrmActivity::query()->create([
                    'lead_id' => $existing->id,
                    'user_id' => $activityUserId,
                    'type' => CrmActivityType::Note,
                    'occurred_at' => now(),
                    'notes' => 'تكرار إرسال من اكتشف احتياجك'."\n".$noteBlock,
                ]);
            }

            return [$this->crmLeads->load($existing), false];
        }

        $lead = CrmLead::query()->create([
            'reference' => $this->crmLeads->generateReference(),
            'full_name' => $data['name'],
            'company_name' => $data['company'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'service_id' => $data['service_id'] ?? null,
            'estimated_budget' => $this->parseBudgetAmount($data['budget'] ?? null),
            'notes' => $noteBlock,
            'status' => CrmLeadStatus::New->value,
            'stage_id' => $stage->id,
            'priority' => CrmLeadPriority::Medium->value,
            'source_id' => $source?->id,
            'score' => 35,
            'needs_attention' => true,
            'tags' => ['needs-discovery'],
        ]);

        $lead = $this->assignment->assignAfterCreate($lead);

        try {
            app(WorkflowAutomationEngine::class)->dispatch('crm.lead.created', [
                'lead_id' => $lead->id,
                'source' => self::SOURCE,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }

        return [$this->crmLeads->load($lead), true];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function formatLeadNotes(array $data): string
    {
        $lines = [
            '—— اكتشف احتياجك ——',
            'الملخص: '.($data['summary'] ?? ''),
            'الميزانية: '.($data['budget'] ?? '—'),
            'الموعد: '.($data['deadline'] ?? '—'),
            'التفاصيل: '.($data['description'] ?? '—'),
        ];

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function buildDescription(array $answers): string
    {
        $map = [
            'need' => 'الاحتياج',
            'project_type' => 'نوع المشروع',
            'service' => 'الخدمة',
            'budget' => 'الميزانية',
            'deadline' => 'الموعد',
            'has_files' => 'ملفات',
        ];

        $parts = [];
        foreach ($map as $key => $label) {
            $value = $this->answerLabel($answers, $key);
            if ($value !== null) {
                $parts[] = $label.': '.$value;
            }
        }

        return implode("\n", $parts);
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function answerLabel(array $answers, string $key): ?string
    {
        $raw = $answers[$key] ?? null;
        if (is_array($raw)) {
            $value = $raw['label'] ?? $raw['value'] ?? null;

            return is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        return is_string($raw) && trim($raw) !== '' ? trim($raw) : null;
    }

    /**
     * @param  array<string, mixed>  $answers
     */
    private function resolveServiceId(array $answers): ?int
    {
        $raw = $answers['service'] ?? null;
        if (is_array($raw) && isset($raw['service_id'])) {
            return (int) $raw['service_id'];
        }

        if (is_array($raw) && isset($raw['id']) && is_string($raw['id']) && str_starts_with($raw['id'], 'service:')) {
            $slug = substr($raw['id'], strlen('service:'));
            if ($slug !== 'other') {
                return Service::query()->where('slug', $slug)->value('id');
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return list<array{id: int, name: string, slug: string}>
     */
    private function recommendServices(array $answers, ?int $selectedId): array
    {
        $query = Service::query()->active()->public()->orderByDesc('is_featured')->orderBy('sort_order')->limit(4);

        $need = $this->answerLabel($answers, 'need');
        $categoryMap = [
            'موقع أو متجر' => 'PRODUCTION',
            'هوية وتصميم' => 'CONTENT',
            'تسويق وحملات' => 'CAMPAIGNS',
            'طباعة وتغليف' => 'PRINTING',
            'فعاليات' => 'OTHER',
        ];

        if ($need !== null && isset($categoryMap[$need])) {
            $query->where('category', $categoryMap[$need]);
        }

        $items = $query->get(['id', 'name', 'slug']);
        if ($selectedId !== null && ! $items->contains('id', $selectedId)) {
            $selected = Service::query()->find($selectedId, ['id', 'name', 'slug']);
            if ($selected !== null) {
                $items->prepend($selected);
            }
        }

        return $items->unique('id')->take(4)->map(fn (Service $service) => [
            'id' => $service->id,
            'name' => $service->name,
            'slug' => $service->slug,
        ])->values()->all();
    }

    private function buildSummary(?string $name, ?string $service, ?string $budget, ?string $deadline): string
    {
        return trim(sprintf(
            '%s · %s · ميزانية %s · موعد %s',
            $name ?: 'عميل',
            $service ?: 'خدمة غير محددة',
            $budget ?: 'غير محدد',
            $deadline ?: 'غير محدد',
        ));
    }

    /**
     * @param  list<UploadedFile>  $files
     * @return list<array{path: string, original_name: string, mime_type: string, size: int}>
     */
    private function storeAttachments(array $files): array
    {
        $stored = [];
        foreach ($files as $file) {
            if (! $file instanceof UploadedFile) {
                continue;
            }
            $ext = strtolower((string) ($file->getClientOriginalExtension() ?: 'bin'));
            $name = Str::uuid()->toString().'.'.$ext;
            $path = $file->storeAs('needs-discovery', $name, 'local');
            if (! is_string($path) || $path === '') {
                continue;
            }
            $stored[] = [
                'path' => $path,
                'original_name' => Str::limit(basename($file->getClientOriginalName()) ?: 'file', 180, ''),
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => (int) ($file->getSize() ?: 0),
            ];
        }

        return $stored;
    }

    private function parseBudgetAmount(?string $budget): ?float
    {
        if ($budget === null) {
            return null;
        }

        return match (true) {
            str_contains($budget, '50,000') && str_contains($budget, 'أكثر') => 50000.0,
            str_contains($budget, '15,000') => 15000.0,
            str_contains($budget, '5,000') && str_contains($budget, 'أقل') => 2500.0,
            str_contains($budget, '5,000') => 10000.0,
            default => null,
        };
    }

    private function generateReference(): string
    {
        $year = now()->format('Y');
        $count = Requirement::query()->whereYear('created_at', (int) $year)->count() + 1;

        return sprintf('RQ-%s-%04d', $year, $count);
    }

    private function notifyOwnersOfRequirement(Requirement $requirement): void
    {
        $owners = User::query()
            ->whereIn('role', [UserRole::Owner->value, UserRole::SalesManager->value, UserRole::AccountManager->value])
            ->where('is_active', true)
            ->limit(10)
            ->get();

        foreach ($owners as $owner) {
            $this->notifier->requirementReceived($requirement, $owner);
        }
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
