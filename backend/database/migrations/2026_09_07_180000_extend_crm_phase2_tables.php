<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_leads', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->after('created_by');
            $table->timestamp('first_contacted_at')->nullable()->after('last_contacted_at');
            $table->boolean('needs_attention')->default(false)->after('archived_at');
            $table->index('archived_at');
        });

        Schema::table('crm_companies', function (Blueprint $table): void {
            $table->string('status')->default('active')->after('notes');
            $table->timestamp('archived_at')->nullable()->after('status');
        });

        Schema::table('crm_contacts', function (Blueprint $table): void {
            $table->foreignId('lead_id')->nullable()->after('company_id')->constrained('crm_leads')->nullOnDelete();
            $table->foreignId('customer_id')->nullable()->after('lead_id')->constrained('users')->nullOnDelete();
        });

        Schema::table('crm_quotations', function (Blueprint $table): void {
            $table->string('public_token')->nullable()->unique()->after('sent_at');
            $table->string('delivery_time')->nullable()->after('public_token');
            $table->decimal('discount_percent', 5, 2)->default(0)->after('discount_amount');
            $table->timestamp('approved_at')->nullable()->after('delivery_time');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->timestamp('accepted_at')->nullable()->after('rejected_at');
            $table->text('rejection_notes')->nullable()->after('accepted_at');
        });

        Schema::table('crm_opportunities', function (Blueprint $table): void {
            $table->timestamp('archived_at')->nullable()->after('project_id');
        });

        Schema::table('files', function (Blueprint $table): void {
            $table->foreignId('crm_lead_id')->nullable()->after('task_id')->constrained('crm_leads')->nullOnDelete();
            $table->foreignId('crm_opportunity_id')->nullable()->after('crm_lead_id')->constrained('crm_opportunities')->nullOnDelete();
            $table->foreignId('crm_quotation_id')->nullable()->after('crm_opportunity_id')->constrained('crm_quotations')->nullOnDelete();
        });

        Schema::create('crm_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('crm_saved_filters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('entity')->default('leads');
            $table->json('filters');
            $table->timestamps();
        });

        Schema::create('crm_assignment_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->string('match_type');
            $table->string('match_value');
            $table->foreignId('assign_to_user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_assignment_rules');
        Schema::dropIfExists('crm_saved_filters');
        Schema::dropIfExists('crm_settings');

        Schema::table('files', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('crm_quotation_id');
            $table->dropConstrainedForeignId('crm_opportunity_id');
            $table->dropConstrainedForeignId('crm_lead_id');
        });

        Schema::table('crm_opportunities', function (Blueprint $table): void {
            $table->dropColumn('archived_at');
        });

        Schema::table('crm_quotations', function (Blueprint $table): void {
            $table->dropUnique(['public_token']);
            $table->dropColumn([
                'public_token',
                'delivery_time',
                'discount_percent',
                'approved_at',
                'rejected_at',
                'accepted_at',
                'rejection_notes',
            ]);
        });

        Schema::table('crm_contacts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('customer_id');
            $table->dropConstrainedForeignId('lead_id');
        });

        Schema::table('crm_companies', function (Blueprint $table): void {
            $table->dropColumn(['status', 'archived_at']);
        });

        Schema::table('crm_leads', function (Blueprint $table): void {
            $table->dropIndex(['archived_at']);
            $table->dropColumn(['archived_at', 'first_contacted_at', 'needs_attention']);
        });
    }
};
