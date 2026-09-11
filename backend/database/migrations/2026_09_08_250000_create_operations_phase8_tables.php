<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('provider', 40);
            $table->string('provider_reference')->nullable();
            $table->string('checkout_reference')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('status', 30)->default('STARTED'); // STARTED|REDIRECTED|FAILED|VERIFIED|ABANDONED
            $table->string('failure_code', 80)->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['payment_id', 'status']);
            $table->index(['provider', 'provider_reference']);
        });

        Schema::create('payment_refunds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('provider', 40); // paytabs|manual
            $table->string('provider_refund_reference')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('status', 30)->default('PENDING'); // PENDING|PROCESSING|CONFIRMED|FAILED|CANCELLED
            $table->string('reason', 500)->nullable();
            $table->boolean('is_manual')->default(false);
            $table->foreignId('requested_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('requested_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->string('failure_message', 500)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['payment_id', 'status']);
            $table->index(['provider', 'provider_refund_reference']);
        });

        Schema::create('customer_communication_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('channel', 20);
            $table->string('type', 60);
            $table->string('recipient', 255)->nullable();
            $table->string('status', 20); // queued|sent|failed|skipped
            $table->string('related_type', 60)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('dedupe_key')->nullable()->unique();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->string('result_summary', 500)->nullable();
            $table->timestamps();

            $table->index(['related_type', 'related_id']);
            $table->index(['type', 'status']);
        });

        Schema::table('printing_deliveries', function (Blueprint $table): void {
            $table->string('contact_name')->nullable()->after('recipient_name');
            $table->string('contact_phone', 40)->nullable()->after('contact_name');
            $table->string('scheduled_window', 120)->nullable()->after('scheduled_at');
            $table->foreignId('proof_file_id')->nullable()->after('notes')->constrained('files')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->string('dispute_status', 40)->nullable()->after('reconciliation_note');
            $table->json('dispute_metadata')->nullable()->after('dispute_status');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn(['dispute_status', 'dispute_metadata']);
        });

        Schema::table('printing_deliveries', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('proof_file_id');
            $table->dropColumn(['contact_name', 'contact_phone', 'scheduled_window']);
        });

        Schema::dropIfExists('customer_communication_deliveries');
        Schema::dropIfExists('payment_refunds');
        Schema::dropIfExists('payment_attempts');
    }
};
