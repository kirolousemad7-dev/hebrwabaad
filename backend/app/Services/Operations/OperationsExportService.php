<?php

namespace App\Services\Operations;

use App\Enums\UserRole;
use App\Models\PrintingRequest;
use App\Models\User;
use App\Services\Operations\Work\UnifiedWorkService;
use App\Services\Printing\PrintingRevenueService;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OperationsExportService
{
    public function __construct(
        private readonly UnifiedWorkService $unifiedWork,
        private readonly PrintingOperationsService $printing,
        private readonly SlaEvaluationService $sla,
        private readonly PrintingRevenueService $printingRevenue,
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function workCsv(User $actor, array $filters = []): StreamedResponse
    {
        $this->assertCanExportWork($actor);

        $result = $this->unifiedWork->list($actor, array_merge([
            'scope' => ($actor->role instanceof UserRole && $actor->role->canManageTeamWork()) ? 'team' : 'mine',
            'bucket' => 'all',
            'page' => 1,
            'per_page' => 50,
            'sort' => 'overdue_first',
        ], $filters, [
            'page' => 1,
            'per_page' => 50,
        ]));

        // Pull up to 2000 rows across pages for export.
        $items = $result['items'];
        $total = (int) ($result['meta']['total'] ?? count($items));
        $page = 2;
        while (count($items) < min(2000, $total) && count($result['items']) > 0) {
            $result = $this->unifiedWork->list($actor, array_merge($filters, [
                'scope' => $filters['scope'] ?? (($actor->role instanceof UserRole && $actor->role->canManageTeamWork()) ? 'team' : 'mine'),
                'bucket' => $filters['bucket'] ?? 'all',
                'page' => $page,
                'per_page' => 50,
                'sort' => $filters['sort'] ?? 'overdue_first',
            ]));
            if ($result['items'] === []) {
                break;
            }
            $items = array_merge($items, $result['items']);
            $page++;
            if ($page > 40) {
                break;
            }
        }

        $headers = ['id', 'source_type', 'source_id', 'title', 'status', 'priority', 'due_at', 'is_overdue', 'assignee', 'project_id', 'href'];
        $rows = array_map(function (array $item) use ($headers): array {
            $assignee = $item['assignee']['name'] ?? ($item['assignees'][0]['name'] ?? '');

            return $this->escapeRow([
                'id' => (string) ($item['id'] ?? ''),
                'source_type' => (string) ($item['source_type'] ?? ''),
                'source_id' => (string) ($item['source_id'] ?? ''),
                'title' => (string) ($item['title'] ?? ''),
                'status' => (string) ($item['status'] ?? ''),
                'priority' => (string) ($item['priority'] ?? ''),
                'due_at' => (string) ($item['due_at'] ?? ($item['starts_at'] ?? '')),
                'is_overdue' => ! empty($item['is_overdue']) ? '1' : '0',
                'assignee' => (string) $assignee,
                'project_id' => (string) ($item['project_id'] ?? ''),
                'href' => (string) ($item['href'] ?? ''),
            ], $headers);
        }, $items);

        return $this->streamCsv('operations-work-'.now()->format('Ymd-His').'.csv', $headers, $rows);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function printingCsv(User $actor, array $filters = []): StreamedResponse
    {
        $this->assertCanExportPrinting($actor);

        $data = $this->printing->list($actor, [
            'category' => $filters['category'] ?? null,
            'status' => $filters['status'] ?? null,
            'approaching_days' => $filters['approaching_days'] ?? 2,
            'q' => $filters['q'] ?? null,
        ]);

        $headers = [
            'id', 'product_name', 'status', 'required_date', 'category',
            'assigned_to', 'department', 'customer', 'created_at',
        ];

        $rows = array_map(function (array $item) use ($headers): array {
            return $this->escapeRow([
                'id' => (string) ($item['id'] ?? ''),
                'product_name' => (string) ($item['product_name'] ?? ''),
                'status' => (string) ($item['status'] ?? ''),
                'required_date' => (string) ($item['required_date'] ?? ''),
                'category' => (string) ($item['category'] ?? ''),
                'assigned_to' => (string) ($item['assigned_to']['name'] ?? ''),
                'department' => (string) ($item['assigned_department']['name'] ?? ''),
                'customer' => (string) ($item['customer']['name'] ?? ($item['customer_name'] ?? '')),
                'created_at' => (string) ($item['created_at'] ?? ''),
            ], $headers);
        }, $data['items'] ?? []);

        return $this->streamCsv('operations-printing-'.now()->format('Ymd-His').'.csv', $headers, $rows);
    }

    public function printingQuotationsCsv(User $actor): StreamedResponse
    {
        $rowsData = $this->printingRevenue->exportRows($actor);

        $headers = [
            'id', 'reference', 'revision', 'status', 'customer', 'product_name',
            'total', 'currency', 'payment_policy', 'valid_until', 'sent_at',
            'accepted_at', 'printing_request_id',
        ];

        $rows = array_map(
            fn (array $item): array => $this->escapeRow($item, $headers),
            $rowsData,
        );

        return $this->streamCsv(
            'operations-printing-quotations-'.now()->format('Ymd-His').'.csv',
            $headers,
            $rows,
        );
    }

    public function slaCsv(User $actor): StreamedResponse
    {
        $this->assertCanExportInsights($actor);

        $eval = $this->sla->evaluateActive(500);
        $headers = [
            'module', 'event_type', 'rule_id', 'subject_type', 'subject_id',
            'status', 'elapsed_minutes', 'target_minutes',
        ];

        $rows = array_map(function (array $sample) use ($headers): array {
            return $this->escapeRow([
                'module' => (string) ($sample['module'] ?? ''),
                'event_type' => (string) ($sample['event_type'] ?? ''),
                'rule_id' => (string) ($sample['rule_id'] ?? ''),
                'subject_type' => (string) ($sample['related_type'] ?? ($sample['subject_type'] ?? '')),
                'subject_id' => (string) ($sample['related_id'] ?? ($sample['subject_id'] ?? '')),
                'status' => (string) ($sample['status'] ?? ''),
                'elapsed_minutes' => (string) ($sample['elapsed_minutes'] ?? ''),
                'target_minutes' => (string) ($sample['target_minutes'] ?? ''),
            ], $headers);
        }, $eval['samples'] ?? []);

        return $this->streamCsv('operations-sla-'.now()->format('Ymd-His').'.csv', $headers, $rows);
    }

    /**
     * Prefix values that could be interpreted as spreadsheet formulas.
     */
    public function escapeFormula(mixed $value): string
    {
        $string = $value === null ? '' : (string) $value;
        if ($string === '') {
            return '';
        }

        $first = $string[0];
        if (in_array($first, ['=', '+', '-', '@'], true)) {
            return "'".$string;
        }

        return $string;
    }

    /**
     * @param  list<string>  $headers
     * @param  array<string, mixed>  $assoc
     * @return list<string>
     */
    private function escapeRow(array $assoc, array $headers): array
    {
        $out = [];
        foreach ($headers as $header) {
            $out[] = $this->escapeFormula($assoc[$header] ?? '');
        }

        return $out;
    }

    /**
     * @param  list<string>  $headers
     * @param  list<list<string>>  $rows
     */
    private function streamCsv(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'w');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }
            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function assertCanExportWork(User $actor): void
    {
        if (! ($actor->role instanceof UserRole) || ! $actor->role->canViewUnifiedWork()) {
            abort(403);
        }
    }

    private function assertCanExportPrinting(User $actor): void
    {
        if (! $actor->can('viewAny', PrintingRequest::class)) {
            abort(403);
        }
    }

    private function assertCanExportInsights(User $actor): void
    {
        $role = $actor->role;
        if (! ($role instanceof UserRole)
            || ! in_array($role, [UserRole::Owner, UserRole::AdminManager, UserRole::AccountManager], true)
        ) {
            abort(403);
        }
    }
}
