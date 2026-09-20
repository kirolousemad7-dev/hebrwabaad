<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table): void {
            $table->json('brief')->nullable()->after('description');
            $table->json('client_profile')->nullable()->after('brief');
            $table->json('requirements')->nullable()->after('client_profile');
            $table->json('scope')->nullable()->after('requirements');
        });

        Schema::create('project_phases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 40)->default('PENDING');
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_client_visible')->default(true);
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'sort_order']);
        });

        Schema::create('project_references', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('type', 40)->default('OTHER');
            $table->string('category', 80)->nullable();
            $table->boolean('is_client_visible')->default(false);
            $table->foreignId('file_id')->nullable()->constrained('files')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'type']);
        });

        Schema::table('project_milestones', function (Blueprint $table): void {
            $table->foreignId('phase_id')->nullable()->after('project_id')->constrained('project_phases')->nullOnDelete();
            $table->text('description')->nullable()->after('title');
            $table->date('starts_at')->nullable()->after('description');
            $table->unsignedInteger('sort_order')->default(0)->after('status');
            $table->boolean('is_client_visible')->default(false)->after('sort_order');
            $table->foreignId('responsible_user_id')->nullable()->after('is_client_visible')->constrained('users')->nullOnDelete();
        });

        Schema::table('tasks', function (Blueprint $table): void {
            $table->foreignId('phase_id')->nullable()->after('project_id')->constrained('project_phases')->nullOnDelete();
            $table->foreignId('milestone_id')->nullable()->after('phase_id')->constrained('project_milestones')->nullOnDelete();
            $table->boolean('is_client_visible')->default(false)->after('calendar_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('phase_id');
            $table->dropConstrainedForeignId('milestone_id');
            $table->dropColumn('is_client_visible');
        });

        Schema::table('project_milestones', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('phase_id');
            $table->dropConstrainedForeignId('responsible_user_id');
            $table->dropColumn(['description', 'starts_at', 'sort_order', 'is_client_visible']);
        });

        Schema::dropIfExists('project_references');
        Schema::dropIfExists('project_phases');

        Schema::table('projects', function (Blueprint $table): void {
            $table->dropColumn(['brief', 'client_profile', 'requirements', 'scope']);
        });
    }
};
