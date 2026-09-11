<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 32)->unique();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->string('source_type', 40);
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('title');
            $table->string('status', 32)->default('NEW');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at');
            $table->date('required_date')->nullable();
            $table->decimal('budget_min', 12, 2)->nullable();
            $table->decimal('budget_max', 12, 2)->nullable();
            $table->string('city', 120)->nullable();
            $table->text('customer_notes')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('information_request')->nullable();
            $table->json('payload')->nullable();
            $table->string('quotation_type', 32)->nullable();
            $table->unsignedBigInteger('quotation_id')->nullable();
            $table->timestamps();

            $table->index(['status', 'requested_at']);
            $table->index(['source_type', 'source_id']);
            $table->index(['customer_id', 'status']);
            $table->index(['assigned_to', 'status']);
        });

        Schema::create('quote_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_request_id')->constrained('quote_requests')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type', 16)->default('system');
            $table->string('event_type', 64);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['quote_request_id', 'id']);
        });

        Schema::create('commercial_quotations', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 40);
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('quote_request_id')->constrained('quote_requests')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('DRAFT');
            $table->string('currency', 3)->default('SAR');
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('shipping_amount', 12, 2)->default(0);
            $table->decimal('rental_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);
            $table->decimal('deposit_required', 12, 2)->default(0);
            $table->string('payment_policy', 16)->default('NONE');
            $table->date('valid_until')->nullable();
            $table->string('execution_duration', 120)->nullable();
            $table->unsignedInteger('revision_count')->nullable();
            $table->text('notes')->nullable();
            $table->text('terms')->nullable();
            $table->text('delivery_terms')->nullable();
            $table->text('internal_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->text('revision_reason')->nullable();
            $table->string('public_token_hash', 64)->nullable()->unique();
            $table->string('public_token_hint', 16)->nullable();
            $table->timestamp('token_revoked_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('viewed_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->foreignId('supersedes_id')->nullable()->constrained('commercial_quotations')->nullOnDelete();
            $table->json('snapshot')->nullable();
            $table->string('tracking_token_hash', 64)->nullable()->unique();
            $table->string('tracking_token_hint', 16)->nullable();
            $table->timestamps();

            $table->unique(['reference', 'revision']);
            $table->index(['quote_request_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index(['status', 'valid_until']);
        });

        Schema::create('commercial_quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commercial_quotation_id')->constrained('commercial_quotations')->cascadeOnDelete();
            $table->string('description');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('subtotal', 12, 2)->default(0);
            $table->string('category', 32)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            // Explicit short name: auto name exceeds MariaDB's 64-char limit.
            $table->index(
                ['commercial_quotation_id', 'sort_order'],
                'cq_items_quotation_sort_idx',
            );
        });

        Schema::create('commercial_quotation_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commercial_quotation_id')->constrained('commercial_quotations')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_type', 16)->default('system');
            $table->string('event_type', 64);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['commercial_quotation_id', 'id']);
        });

        Schema::table('files', function (Blueprint $table) {
            $table->foreignId('quote_request_id')->nullable()->after('crm_quotation_id')->constrained('quote_requests')->nullOnDelete();
            $table->foreignId('commercial_quotation_id')->nullable()->after('quote_request_id')->constrained('commercial_quotations')->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('commercial_quotation_id')->nullable()->after('printing_quotation_id')->constrained('commercial_quotations')->nullOnDelete();
            $table->index('commercial_quotation_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('commercial_quotation_id');
        });

        Schema::table('files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('commercial_quotation_id');
            $table->dropConstrainedForeignId('quote_request_id');
        });

        Schema::dropIfExists('commercial_quotation_events');
        Schema::dropIfExists('commercial_quotation_items');
        Schema::dropIfExists('commercial_quotations');
        Schema::dropIfExists('quote_request_events');
        Schema::dropIfExists('quote_requests');
    }
};
