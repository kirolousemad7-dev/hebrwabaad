<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->foreignId('department_id')->nullable()->after('sort_order')->constrained('departments')->nullOnDelete();
            $table->string('task_title_template')->nullable()->after('department_id');
            $table->string('default_task_priority', 20)->nullable()->after('task_title_template');
            $table->boolean('requires_review')->default(false)->after('default_task_priority');
            $table->boolean('requires_customer_approval')->default(false)->after('requires_review');
            $table->json('checklist_template')->nullable()->after('requires_customer_approval');
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('department_id')->nullable()->after('project_id')->constrained('departments')->nullOnDelete();
            $table->foreignId('order_item_id')->nullable()->after('department_id')->constrained('order_items')->nullOnDelete();
            $table->string('source', 40)->nullable()->after('order_item_id');
            $table->foreignId('assigned_to')->nullable()->change();
            $table->unique('order_item_id');
            $table->index(['department_id', 'status']);
            $table->index('source');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropUnique(['order_item_id']);
            $table->dropIndex(['department_id', 'status']);
            $table->dropIndex(['source']);
            $table->dropConstrainedForeignId('order_item_id');
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn('source');
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('department_id');
            $table->dropColumn([
                'task_title_template',
                'default_task_priority',
                'requires_review',
                'requires_customer_approval',
                'checklist_template',
            ]);
        });
    }
};
