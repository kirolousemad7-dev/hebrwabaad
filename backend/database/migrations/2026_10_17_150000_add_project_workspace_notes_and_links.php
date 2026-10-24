<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['project_id', 'task_id', 'id']);
        });

        Schema::create('project_reference_task', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_reference_id')->constrained()->cascadeOnDelete();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['project_reference_id', 'task_id'], 'project_reference_task_unique');
        });

        Schema::table('files', function (Blueprint $table): void {
            $table->string('category', 40)->nullable()->after('is_client_visible');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table): void {
            $table->dropColumn('category');
        });

        Schema::dropIfExists('project_reference_task');
        Schema::dropIfExists('workspace_notes');
    }
};
