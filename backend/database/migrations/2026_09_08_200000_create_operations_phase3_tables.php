<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->foreignId('department_id')->nullable()->after('role')->constrained('departments')->nullOnDelete();
            $table->index('department_id');
        });

        Schema::table('calendar_items', function (Blueprint $table): void {
            $table->foreignId('department_id')->nullable()->after('source')->constrained('departments')->nullOnDelete();
            $table->index('department_id');
        });

        Schema::create('project_members', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 40)->default('member'); // manager|member
            $table->timestamps();
            $table->unique(['project_id', 'user_id']);
        });

        Schema::create('workflow_automations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('trigger', 80);
            $table->json('conditions')->nullable();
            $table->json('actions');
            $table->boolean('is_active')->default(false);
            $table->boolean('is_template')->default(false);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['trigger', 'is_active']);
        });

        Schema::create('workflow_automation_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('automation_id')->constrained('workflow_automations')->cascadeOnDelete();
            $table->string('trigger', 80);
            $table->string('idempotency_key')->unique();
            $table->string('source_type', 60)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('status', 20); // success|skipped|failed
            $table->json('result')->nullable();
            $table->timestamp('executed_at');
            $table->timestamps();

            $table->index(['automation_id', 'executed_at']);
            $table->index(['source_type', 'source_id']);
        });

        Schema::create('approval_requests', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 60);
            $table->string('related_type', 60);
            $table->unsignedBigInteger('related_id');
            $table->string('title');
            $table->text('notes')->nullable();
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_to')->constrained('users')->cascadeOnDelete();
            $table->string('status', 30)->default('PENDING'); // PENDING|APPROVED|REJECTED
            $table->text('decision_notes')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();

            $table->index(['assigned_to', 'status']);
            $table->index(['related_type', 'related_id']);
        });

        Schema::create('user_notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->unique();
            $table->boolean('calendar_assignments')->default(true);
            $table->boolean('task_reminders')->default(true);
            $table->boolean('overdue_alerts')->default(true);
            $table->boolean('mentions')->default(true);
            $table->boolean('automation_notifications')->default(true);
            $table->boolean('project_alerts')->default(true);
            $table->boolean('daily_digest')->default(false);
            $table->boolean('approval_requests')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_notification_preferences');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('workflow_automation_runs');
        Schema::dropIfExists('workflow_automations');
        Schema::dropIfExists('project_members');

        Schema::table('calendar_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::dropIfExists('departments');
    }
};
