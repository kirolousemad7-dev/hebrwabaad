<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('project_activities')) {
            return;
        }

        Schema::create('project_activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('actor_customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 100);
            $table->string('entity_type', 64)->nullable();
            $table->unsignedBigInteger('entity_id')->nullable();
            $table->string('description')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('is_client_visible')->default(false);
            $table->timestamps();

            $table->index(['project_id', 'created_at']);
            $table->index('actor_user_id');
            $table->index('actor_customer_id');
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_activities');
    }
};
