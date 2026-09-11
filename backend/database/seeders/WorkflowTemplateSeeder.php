<?php

namespace Database\Seeders;

use App\Enums\CalendarItemPriority;
use App\Enums\UserRole;
use App\Enums\WorkflowTrigger;
use App\Models\User;
use App\Models\WorkflowAutomation;
use Illuminate\Database\Seeder;

/**
 * Inactive starter automation templates. Not auto-run from DatabaseSeeder.
 */
class WorkflowTemplateSeeder extends Seeder
{
    public function run(?User $actor = null): void
    {
        $creator = $actor ?? User::query()
            ->where('role', UserRole::Owner)
            ->where('is_active', true)
            ->orderBy('id')
            ->first();

        if ($creator === null) {
            return;
        }

        $templates = [
            [
                'name' => 'مهمة متابعة عند إنشاء طلب',
                'trigger' => WorkflowTrigger::OrderCreated->value,
                'actions' => [[
                    'type' => 'create_task',
                    'title' => 'متابعة طلب جديد',
                    'priority' => CalendarItemPriority::High->value,
                ]],
            ],
            [
                'name' => 'مهمة عند ربح فرصة',
                'trigger' => WorkflowTrigger::CrmOpportunityWon->value,
                'actions' => [[
                    'type' => 'create_task',
                    'title' => 'بدء تنفيذ الصفقة الرابحة',
                    'priority' => CalendarItemPriority::High->value,
                ]],
            ],
            [
                'name' => 'تذكير مهمة متأخرة',
                'trigger' => WorkflowTrigger::CalendarTaskOverdue->value,
                'actions' => [[
                    'type' => 'create_reminder',
                    'title' => 'متابعة مهمة متأخرة',
                ]],
            ],
            [
                'name' => 'اقتراب موعد مشروع',
                'trigger' => WorkflowTrigger::ProjectDeadlineApproaching->value,
                'actions' => [[
                    'type' => 'create_event',
                    'title' => 'مراجعة موعد مشروع',
                ]],
            ],
            [
                'name' => 'انتهاء صلاحية عرض سعر',
                'trigger' => WorkflowTrigger::CrmQuotationExpiring->value,
                'actions' => [[
                    'type' => 'create_task',
                    'title' => 'متابعة عرض سعر قبل الانتهاء',
                    'priority' => CalendarItemPriority::Urgent->value,
                ]],
            ],
        ];

        foreach ($templates as $template) {
            WorkflowAutomation::query()->firstOrCreate(
                [
                    'name' => $template['name'],
                    'trigger' => $template['trigger'],
                    'is_template' => true,
                    'created_by' => $creator->id,
                ],
                [
                    'conditions' => [],
                    'actions' => $template['actions'],
                    'is_active' => false,
                ],
            );
        }
    }
}
