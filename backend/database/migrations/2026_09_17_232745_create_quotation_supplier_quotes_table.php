<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quotation_supplier_quotes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('commercial_quotation_id')->constrained('commercial_quotations')->cascadeOnDelete();
            $table->foreignId('commercial_quotation_item_id')->constrained('commercial_quotation_items')->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained('suppliers')->restrictOnDelete();
            $table->decimal('cost', 12, 2)->nullable();
            $table->string('currency', 3)->default('EGP');
            $table->date('valid_until')->nullable();
            $table->unsignedInteger('delivery_days')->nullable();
            $table->text('notes')->nullable();
            $table->json('attachments')->nullable();
            $table->string('status', 32)->default('REQUESTED');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('selected_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->foreignId('replaced_by_id')->nullable()->constrained('quotation_supplier_quotes')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['commercial_quotation_item_id', 'supplier_id'],
                'qsq_item_supplier_unique',
            );
            $table->index(['supplier_id', 'status'], 'qsq_supplier_status_idx');
            $table->index(['commercial_quotation_id', 'status'], 'qsq_quotation_status_idx');
        });

        Schema::table('commercial_quotation_items', function (Blueprint $table): void {
            $table->foreignId('selected_supplier_quote_id')
                ->nullable()
                ->after('meta')
                ->constrained('quotation_supplier_quotes')
                ->nullOnDelete();
        });

        Schema::table('commercial_quotations', function (Blueprint $table): void {
            $table->foreignId('execution_project_id')
                ->nullable()
                ->after('order_id')
                ->constrained('projects')
                ->nullOnDelete();
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('supplier_id')
                ->nullable()
                ->after('order_item_id')
                ->constrained('suppliers')
                ->nullOnDelete();
            $table->foreignId('quotation_supplier_quote_id')
                ->nullable()
                ->after('supplier_id')
                ->constrained('quotation_supplier_quotes')
                ->nullOnDelete();
            $table->foreignId('commercial_quotation_item_id')
                ->nullable()
                ->after('quotation_supplier_quote_id')
                ->constrained('commercial_quotation_items')
                ->nullOnDelete();
            $table->index('supplier_id', 'tasks_supplier_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex('tasks_supplier_id_idx');
            $table->dropConstrainedForeignId('commercial_quotation_item_id');
            $table->dropConstrainedForeignId('quotation_supplier_quote_id');
            $table->dropConstrainedForeignId('supplier_id');
        });

        Schema::table('commercial_quotations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('execution_project_id');
        });

        Schema::table('commercial_quotation_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('selected_supplier_quote_id');
        });

        Schema::dropIfExists('quotation_supplier_quotes');
    }
};
