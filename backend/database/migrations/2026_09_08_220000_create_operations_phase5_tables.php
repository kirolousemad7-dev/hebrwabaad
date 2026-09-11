<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('printing_requests', function (Blueprint $table): void {
            $table->timestamp('status_changed_at')->nullable()->after('status');
            $table->foreignId('assigned_to')->nullable()->after('quoted_by')->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_department_id')->nullable()->after('assigned_to')->constrained('departments')->nullOnDelete();
            $table->index(['status', 'required_date']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('printing_status_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('printing_request_id')->constrained('printing_requests')->cascadeOnDelete();
            $table->string('from_status', 40)->nullable();
            $table->string('to_status', 40);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['printing_request_id', 'created_at']);
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('calendar_item_id')->nullable()->after('deadline')->constrained('calendar_items')->nullOnDelete();
            $table->index('calendar_item_id');
        });

        Schema::create('business_calendars', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('timezone', 64)->default('Asia/Riyadh');
            $table->unsignedTinyInteger('week_start')->default(0); // 0=Sunday
            $table->json('working_days'); // e.g. [0,1,2,3,4]
            $table->time('work_start')->default('09:00:00');
            $table->time('work_end')->default('17:00:00');
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('business_calendar_holidays', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('business_calendar_id')->constrained('business_calendars')->cascadeOnDelete();
            $table->date('date');
            $table->string('name');
            $table->timestamps();

            $table->unique(['business_calendar_id', 'date']);
        });

        Schema::create('outbound_webhooks', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('url');
            $table->json('events');
            $table->text('secret_encrypted');
            $table->string('secret_hint', 12)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_delivery_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->uuid('delivery_id')->unique();
            $table->foreignId('outbound_webhook_id')->constrained('outbound_webhooks')->cascadeOnDelete();
            $table->string('event', 80);
            $table->string('idempotency_key')->unique();
            $table->json('payload');
            $table->string('status', 20)->default('pending'); // pending|delivered|failed
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('response_summary', 500)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['outbound_webhook_id', 'status']);
            $table->index(['status', 'next_retry_at']);
        });

        Schema::create('operations_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 80);
            $table->string('subject_type', 60)->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operations_audit_logs');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('outbound_webhooks');
        Schema::dropIfExists('business_calendar_holidays');
        Schema::dropIfExists('business_calendars');

        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('calendar_item_id');
        });

        Schema::dropIfExists('printing_status_histories');

        Schema::table('printing_requests', function (Blueprint $table): void {
            $table->dropIndex(['assigned_to', 'status']);
            $table->dropIndex(['status', 'required_date']);
            $table->dropConstrainedForeignId('assigned_department_id');
            $table->dropConstrainedForeignId('assigned_to');
            $table->dropColumn('status_changed_at');
        });
    }
};
