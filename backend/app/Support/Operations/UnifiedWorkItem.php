<?php

namespace App\Support\Operations;

/**
 * Normalized work item from Task or CalendarItem (type=TASK).
 *
 * @phpstan-type Capabilities array{
 *     can_edit: bool,
 *     can_complete: bool,
 *     can_assign: bool,
 *     can_reschedule: bool,
 *     can_start: bool
 * }
 */
readonly class UnifiedWorkItem
{
    /**
     * @param  list<int>  $assigneeIds
     * @param  Capabilities  $capabilities
     */
    public function __construct(
        public string $id,
        public string $sourceType,
        public int $sourceId,
        public string $title,
        public ?string $description,
        public string $status,
        public string $priority,
        public ?string $dueAt,
        public ?string $startsAt,
        public array $assigneeIds,
        public ?int $departmentId,
        public ?int $projectId,
        public ?string $relatedType,
        public ?int $relatedId,
        public ?int $createdBy,
        public bool $isOverdue,
        public bool $isCompleted,
        public bool $isBlocked,
        public ?string $blockedLabel,
        public ?string $href,
        public array $capabilities,
        public string $sourceBadge,
    ) {}

    /**
     * @param  array{
     *     id: string,
     *     source_type: string,
     *     source_id: int,
     *     title: string,
     *     description?: ?string,
     *     status: string,
     *     priority: string,
     *     due_at?: ?string,
     *     starts_at?: ?string,
     *     assignee_ids?: list<int>,
     *     department_id?: ?int,
     *     project_id?: ?int,
     *     related_type?: ?string,
     *     related_id?: ?int,
     *     created_by?: ?int,
     *     is_overdue?: bool,
     *     is_completed?: bool,
     *     is_blocked?: bool,
     *     blocked_label?: ?string,
     *     href?: ?string,
     *     capabilities?: Capabilities,
     *     source_badge?: string
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            id: $data['id'],
            sourceType: $data['source_type'],
            sourceId: (int) $data['source_id'],
            title: $data['title'],
            description: $data['description'] ?? null,
            status: $data['status'],
            priority: $data['priority'],
            dueAt: $data['due_at'] ?? null,
            startsAt: $data['starts_at'] ?? null,
            assigneeIds: array_values(array_map('intval', $data['assignee_ids'] ?? [])),
            departmentId: isset($data['department_id']) ? ($data['department_id'] !== null ? (int) $data['department_id'] : null) : null,
            projectId: isset($data['project_id']) ? ($data['project_id'] !== null ? (int) $data['project_id'] : null) : null,
            relatedType: $data['related_type'] ?? null,
            relatedId: isset($data['related_id']) ? ($data['related_id'] !== null ? (int) $data['related_id'] : null) : null,
            createdBy: isset($data['created_by']) ? ($data['created_by'] !== null ? (int) $data['created_by'] : null) : null,
            isOverdue: (bool) ($data['is_overdue'] ?? false),
            isCompleted: (bool) ($data['is_completed'] ?? false),
            isBlocked: (bool) ($data['is_blocked'] ?? false),
            blockedLabel: $data['blocked_label'] ?? null,
            href: $data['href'] ?? null,
            capabilities: array_merge([
                'can_edit' => false,
                'can_complete' => false,
                'can_assign' => false,
                'can_reschedule' => false,
                'can_start' => false,
            ], $data['capabilities'] ?? []),
            sourceBadge: $data['source_badge'] ?? $data['source_type'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'source_type' => $this->sourceType,
            'source_id' => $this->sourceId,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status,
            'priority' => $this->priority,
            'due_at' => $this->dueAt,
            'starts_at' => $this->startsAt,
            'assignee_ids' => $this->assigneeIds,
            'department_id' => $this->departmentId,
            'project_id' => $this->projectId,
            'related_type' => $this->relatedType,
            'related_id' => $this->relatedId,
            'created_by' => $this->createdBy,
            'is_overdue' => $this->isOverdue,
            'is_completed' => $this->isCompleted,
            'is_blocked' => $this->isBlocked,
            'blocked_label' => $this->blockedLabel,
            'href' => $this->href,
            'capabilities' => $this->capabilities,
            'source_badge' => $this->sourceBadge,
        ];
    }
}
