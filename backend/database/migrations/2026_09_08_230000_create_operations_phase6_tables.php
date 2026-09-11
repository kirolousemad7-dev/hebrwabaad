<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printing_quotations', function (Blueprint $table): void {
            $table->id();
            $table->string('reference')->unique();
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('printing_request_id')->constrained('printing_requests')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('status', 30)->default('DRAFT'); // DRAFT|SENT|VIEWED|ACCEPTED|REJECTED|EXPIRED|CANCELLED
            $table->string('currency', 3)->default('SAR');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->decimal('deposit_required', 12, 2)->nullable();
            $table->string('payment_policy', 40)->default('FULL'); // NONE|DEPOSIT|FULL
            $table->date('valid_until')->nullable();
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->string('public_token_hash', 64)->nullable()->unique();
            $table->string('public_token_hint', 12)->nullable();
            $table->timestamp('token_revoked_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->foreignId('supersedes_id')->nullable()->constrained('printing_quotations')->nullOnDelete();
            $table->json('snapshot')->nullable(); // immutable commercial snapshot for accepted PDF
            $table->string('tracking_token_hash', 64)->nullable()->unique();
            $table->string('tracking_token_hint', 12)->nullable();
            $table->timestamps();

            $table->index(['printing_request_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index(['status', 'valid_until']);
        });

        Schema::create('printing_quotation_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('printing_quotation_id')->constrained('printing_quotations')->cascadeOnDelete();
            $table->string('event', 60);
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type', 20)->default('staff'); // staff|customer|system
            $table->json('meta')->nullable();
            $table->timestamps();

            // Explicit short name: auto name is exactly 64 chars (MariaDB limit).
            $table->index(
                ['printing_quotation_id', 'created_at'],
                'pq_events_quotation_created_idx',
            );
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('printing_quotation_id')->nullable()->after('order_id')->constrained('printing_quotations')->nullOnDelete();
            $table->index('printing_quotation_id');
        });

        // Allow payments without order when linked to printing quotation (order_id stays nullable if not already)
        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('order_id')->nullable()->change();
        });

        Schema::table('printing_requests', function (Blueprint $table): void {
            $table->string('delivery_method', 40)->nullable()->after('assigned_department_id');
            $table->text('delivery_notes')->nullable()->after('delivery_method');
            $table->timestamp('delivered_at')->nullable()->after('delivery_notes');
            $table->string('received_by', 255)->nullable()->after('delivered_at');
            $table->string('payment_policy', 40)->nullable()->after('received_by'); // override default
        });

        Schema::table('business_calendars', function (Blueprint $table): void {
            $table->foreignId('department_id')->nullable()->after('created_by')->constrained('departments')->nullOnDelete();
            $table->index('department_id');
        });

        Schema::table('operational_sla_rules', function (Blueprint $table): void {
            $table->foreignId('business_calendar_id')->nullable()->after('department_id')->constrained('business_calendars')->nullOnDelete();
        });

        Schema::create('inbound_webhook_integrations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('integration_type', 60); // allowlisted handler key
            $table->string('secret_encrypted');
            $table->string('secret_hint', 12)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('inbound_webhook_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('inbound_webhook_integration_id')->nullable()->constrained('inbound_webhook_integrations')->nullOnDelete();
            $table->string('integration_type', 60);
            $table->string('event', 80);
            $table->string('delivery_id')->unique();
            $table->string('status', 20); // received|processed|failed|rejected
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('result_summary', 500)->nullable();
            $table->json('payload_meta')->nullable(); // non-sensitive subset
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['integration_type', 'received_at']);
        });

        Schema::create('managed_file_visibilities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('managed_file_id')->constrained('files')->cascadeOnDelete();
            $table->boolean('customer_visible')->default(false);
            $table->timestamps();

            $table->unique('managed_file_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('managed_file_visibilities');
        Schema::dropIfExists('inbound_webhook_receipts');
        Schema::dropIfExists('inbound_webhook_integrations');

        Schema::table('operational_sla_rules', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('business_calendar_id');
        });

        Schema::table('business_calendars', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('department_id');
        });

        Schema::table('printing_requests', function (Blueprint $table): void {
            $table->dropColumn([
                'delivery_method',
                'delivery_notes',
                'delivered_at',
                'received_by',
                'payment_policy',
            ]);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('printing_quotation_id');
        });

        Schema::dropIfExists('printing_quotation_events');
        Schema::dropIfExists('printing_quotations');
    }
};
