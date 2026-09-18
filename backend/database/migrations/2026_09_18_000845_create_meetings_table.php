<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meetings', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 32);
            $table->string('meeting_id')->nullable();
            $table->string('join_url', 1024)->nullable();
            $table->string('host_url', 1024)->nullable();
            $table->timestamp('start_at');
            $table->timestamp('end_at')->nullable();
            $table->string('timezone', 64)->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 32)->default('SCHEDULED');
            $table->string('external_event_id')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('commercial_quotation_id')->nullable()->constrained('commercial_quotations')->nullOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained('suppliers')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('include_customer')->default(false);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['provider', 'status'], 'meetings_provider_status_idx');
            $table->index(['task_id', 'status'], 'meetings_task_status_idx');
            $table->index(['start_at'], 'meetings_start_at_idx');
            $table->index(['meeting_id'], 'meetings_meeting_id_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meetings');
    }
};
