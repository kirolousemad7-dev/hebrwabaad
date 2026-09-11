<?php

namespace App\Console\Commands;

use App\Enums\PrintingRequestStatus;
use App\Enums\WorkflowTrigger;
use App\Models\CrmQuotation;
use App\Models\PrintingRequest;
use App\Models\Project;
use App\Services\Workflow\WorkflowAutomationEngine;
use Illuminate\Console\Command;

class RunScheduledWorkflowTriggers extends Command
{
    protected $signature = 'workflows:run-scheduled-triggers {--printing-days=1 : Days ahead for printing.required_date_approaching}';

    protected $description = 'Dispatch deadline-approaching, quotation-expiring, and printing approaching workflow triggers';

    public function handle(WorkflowAutomationEngine $engine): int
    {
        $tomorrow = now()->addDay()->toDateString();
        $dispatched = 0;

        $projects = Project::query()
            ->whereDate('deadline', $tomorrow)
            ->whereNotIn('status', ['COMPLETED', 'CANCELLED'])
            ->limit(200)
            ->get();

        foreach ($projects as $project) {
            $engine->dispatch(WorkflowTrigger::ProjectDeadlineApproaching->value, [
                'source_type' => 'project',
                'source_id' => $project->id,
                'title' => 'اقتراب موعد مشروع: '.$project->title,
                'related_type' => 'project',
                'related_id' => $project->id,
                'assignee_ids' => array_filter([$project->account_manager_id]),
                'payload' => [
                    'project_id' => $project->id,
                    'deadline' => $project->deadline?->toDateString(),
                ],
            ], 'day:'.$tomorrow);
            $dispatched++;
        }

        $quotations = CrmQuotation::query()
            ->whereDate('valid_until', $tomorrow)
            ->limit(200)
            ->get();

        foreach ($quotations as $quotation) {
            $engine->dispatch(WorkflowTrigger::CrmQuotationExpiring->value, [
                'source_type' => 'crm_quotation',
                'source_id' => $quotation->id,
                'title' => 'عرض سعر ينتهي غداً',
                'related_type' => 'crm_quotation',
                'related_id' => $quotation->id,
                'payload' => [
                    'quotation_id' => $quotation->id,
                    'valid_until' => $quotation->valid_until?->toDateString(),
                ],
            ], 'day:'.$tomorrow);
            $dispatched++;
        }

        $dispatched += $this->dispatchPrintingApproaching($engine);

        $this->info("Dispatched {$dispatched} scheduled workflow trigger(s).");

        return self::SUCCESS;
    }

    private function dispatchPrintingApproaching(WorkflowAutomationEngine $engine): int
    {
        $days = max(0, (int) $this->option('printing-days'));
        $targetDate = now()->addDays($days)->toDateString();
        $dispatched = 0;

        $requests = PrintingRequest::query()
            ->whereIn('status', PrintingRequestStatus::openValues())
            ->whereDate('required_date', $targetDate)
            ->limit(200)
            ->get();

        foreach ($requests as $request) {
            $engine->dispatch(WorkflowTrigger::PrintingRequiredDateApproaching->value, [
                'source_type' => 'printing_request',
                'source_id' => $request->id,
                'title' => 'اقتراب موعد طباعة: '.$request->product_name,
                'related_type' => 'printing_request',
                'related_id' => $request->id,
                'assignee_ids' => array_filter([$request->assigned_to, $request->quoted_by]),
                'payload' => [
                    'printing_request_id' => $request->id,
                    'required_date' => $request->required_date?->toDateString(),
                ],
            ], 'day:'.$targetDate);
            $dispatched++;
        }

        $dispatched += $this->dispatchPrintingOverdue($engine);

        return $dispatched;
    }

    private function dispatchPrintingOverdue(WorkflowAutomationEngine $engine): int
    {
        $today = now()->toDateString();
        $dispatched = 0;

        $requests = PrintingRequest::query()
            ->whereIn('status', PrintingRequestStatus::openValues())
            ->whereDate('required_date', '<', $today)
            ->limit(200)
            ->get();

        foreach ($requests as $request) {
            $engine->dispatch(WorkflowTrigger::PrintingOverdue->value, [
                'source_type' => 'printing_request',
                'source_id' => $request->id,
                'title' => 'طباعة متأخرة: '.$request->product_name,
                'related_type' => 'printing_request',
                'related_id' => $request->id,
                'assignee_ids' => array_filter([$request->assigned_to, $request->quoted_by]),
                'payload' => [
                    'printing_request_id' => $request->id,
                    'required_date' => $request->required_date?->toDateString(),
                    'status' => $request->status instanceof PrintingRequestStatus
                        ? $request->status->value
                        : (string) $request->status,
                ],
            ], 'day:'.$today);
            $dispatched++;
        }

        return $dispatched;
    }
}
