<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('calendar_items', function (Blueprint $table): void {
            $table->string('recurrence_rule', 255)->nullable()->after('completed_at');
            $table->timestamp('recurrence_until')->nullable()->after('recurrence_rule');
            $table->unsignedInteger('recurrence_count')->nullable()->after('recurrence_until');
            $table->foreignId('recurrence_parent_id')->nullable()->after('recurrence_count')
                ->constrained('calendar_items')->nullOnDelete();
            $table->json('recurrence_exceptions')->nullable()->after('recurrence_parent_id');
            $table->timestamp('recurrence_instance_at')->nullable()->after('recurrence_exceptions');
            $table->string('location')->nullable()->after('recurrence_instance_at');
            $table->string('meeting_url', 500)->nullable()->after('location');
            $table->foreignId('blocked_by_id')->nullable()->after('meeting_url')
                ->constrained('calendar_items')->nullOnDelete();
            $table->json('checklist')->nullable()->after('blocked_by_id');

            $table->index('recurrence_parent_id');
            $table->index('recurrence_rule');
            $table->index('blocked_by_id');
        });

        Schema::table('files', function (Blueprint $table): void {
            $table->foreignId('calendar_item_id')->nullable()->after('crm_quotation_id')
                ->constrained('calendar_items')->nullOnDelete();
            $table->index('calendar_item_id');
        });

        Schema::create('calendar_item_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('calendar_item_id')->constrained('calendar_items')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('body');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['calendar_item_id', 'created_at']);
        });

        Schema::create('calendar_item_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('calendar_item_id')->constrained('calendar_items')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 60);
            $table->string('summary');
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['calendar_item_id', 'created_at']);
        });

        Schema::create('calendar_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('type', 40);
            $table->string('priority', 20)->default('MEDIUM');
            $table->string('title_pattern')->nullable();
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('default_duration_minutes')->default(60);
            $table->json('default_reminders')->nullable();
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('calendar_saved_filters', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->json('filters');
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('calendar_user_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->unique();
            $table->string('default_view', 20)->default('month');
            $table->unsignedTinyInteger('week_starts_on')->default(6); // Saturday (Saudi-friendly)
            $table->time('workday_start')->default('08:00:00');
            $table->time('workday_end')->default('18:00:00');
            $table->boolean('daily_digest')->default(false);
            $table->boolean('end_of_day_digest')->default(false);
            $table->boolean('show_completed')->default(true);
            $table->string('default_scope', 10)->default('mine');
            $table->json('default_reminders')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_user_settings');
        Schema::dropIfExists('calendar_saved_filters');
        Schema::dropIfExists('calendar_templates');
        Schema::dropIfExists('calendar_item_activities');
        Schema::dropIfExists('calendar_item_comments');

        Schema::table('files', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('calendar_item_id');
        });

        Schema::table('calendar_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('blocked_by_id');
            $table->dropConstrainedForeignId('recurrence_parent_id');
            $table->dropColumn([
                'recurrence_rule',
                'recurrence_until',
                'recurrence_count',
                'recurrence_exceptions',
                'recurrence_instance_at',
                'location',
                'meeting_url',
                'checklist',
            ]);
        });
    }
};
