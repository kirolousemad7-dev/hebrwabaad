<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_portal_accesses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('token_hint', 12)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamp('last_accessed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['customer_id', 'revoked_at']);
        });

        Schema::create('printing_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('printing_request_id')->constrained('printing_requests')->cascadeOnDelete();
            $table->string('method', 40); // pickup|manual_delivery
            $table->string('provider', 40)->default('MANUAL');
            $table->string('status', 40)->default('PENDING'); // PENDING|SCHEDULED|OUT_FOR_DELIVERY|DELIVERED|CANCELLED
            $table->string('external_reference')->nullable();
            $table->string('tracking_url')->nullable();
            $table->string('recipient_name')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['printing_request_id', 'status']);
        });

        Schema::create('customer_communication_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 20); // email|database
            $table->string('template', 60);
            $table->string('status', 20); // queued|sent|failed|skipped
            $table->string('related_type', 60)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('result_summary', 500)->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['related_type', 'related_id']);
            $table->index(['channel', 'status']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->string('provider_status', 40)->nullable()->after('provider');
            $table->timestamp('last_reconciled_at')->nullable()->after('verified_at');
            $table->string('reconciliation_note', 500)->nullable()->after('last_reconciled_at');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn(['provider_status', 'last_reconciled_at', 'reconciliation_note']);
        });

        Schema::dropIfExists('customer_communication_logs');
        Schema::dropIfExists('printing_deliveries');
        Schema::dropIfExists('customer_portal_accesses');
    }
};
