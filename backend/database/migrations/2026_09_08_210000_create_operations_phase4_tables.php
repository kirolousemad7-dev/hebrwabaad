<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_notification_preferences', function (Blueprint $table): void {
            $table->boolean('quiet_hours_enabled')->default(false)->after('approval_requests');
            $table->time('quiet_hours_start')->nullable()->after('quiet_hours_enabled');
            $table->time('quiet_hours_end')->nullable()->after('quiet_hours_start');
            $table->string('quiet_hours_timezone', 64)->nullable()->after('quiet_hours_end');
        });

        Schema::create('operational_saved_views', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('view_type', 40)->default('work'); // work|printing|projects|command_center
            $table->json('filters');
            $table->json('sort')->nullable();
            $table->boolean('is_pinned')->default(false);
            $table->boolean('is_shared')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'view_type', 'is_pinned']);
        });

        Schema::create('operational_attention_snoozes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('attention_key', 190);
            $table->timestamp('snoozed_until');
            $table->timestamps();

            $table->unique(['user_id', 'attention_key']);
            $table->index(['user_id', 'snoozed_until']);
        });

        Schema::create('operational_escalation_states', function (Blueprint $table): void {
            $table->id();
            $table->string('source_type', 60);
            $table->unsignedBigInteger('source_id');
            $table->string('rule_key', 80);
            $table->unsignedTinyInteger('level')->default(0);
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamp('next_eligible_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['source_type', 'source_id', 'rule_key']);
            $table->index(['next_eligible_at', 'level']);
        });

        Schema::create('operational_sla_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('module', 60);
            $table->string('event_type', 80);
            $table->unsignedInteger('target_minutes');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['module', 'event_type', 'is_active']);
        });

        Schema::create('project_milestones', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->string('title');
            $table->date('due_date')->nullable();
            $table->string('status', 30)->default('PENDING'); // PENDING|DONE|MISSED
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'status', 'due_date']);
        });

        Schema::table('workflow_automations', function (Blueprint $table): void {
            $table->unsignedTinyInteger('max_depth')->default(3)->after('is_template');
            $table->unsignedSmallInteger('max_actions_per_run')->default(10)->after('max_depth');
        });

        Schema::table('workflow_automation_runs', function (Blueprint $table): void {
            $table->unsignedTinyInteger('depth')->default(0)->after('status');
            $table->unsignedBigInteger('origin_run_id')->nullable()->after('depth');
            $table->json('trigger_chain')->nullable()->after('origin_run_id');
            $table->boolean('is_dry_run')->default(false)->after('trigger_chain');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_automation_runs', function (Blueprint $table): void {
            $table->dropColumn(['depth', 'origin_run_id', 'trigger_chain', 'is_dry_run']);
        });

        Schema::table('workflow_automations', function (Blueprint $table): void {
            $table->dropColumn(['max_depth', 'max_actions_per_run']);
        });

        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('operational_sla_rules');
        Schema::dropIfExists('operational_escalation_states');
        Schema::dropIfExists('operational_attention_snoozes');
        Schema::dropIfExists('operational_saved_views');

        Schema::table('user_notification_preferences', function (Blueprint $table): void {
            $table->dropColumn([
                'quiet_hours_enabled',
                'quiet_hours_start',
                'quiet_hours_end',
                'quiet_hours_timezone',
            ]);
        });
    }
};
