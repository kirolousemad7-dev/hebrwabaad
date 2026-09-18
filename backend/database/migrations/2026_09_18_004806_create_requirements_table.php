<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('requirements', function (Blueprint $table) {
            $table->id();
            $table->string('reference')->unique();
            $table->string('name');
            $table->string('phone', 40)->nullable();
            $table->string('email')->nullable();
            $table->string('company')->nullable();
            $table->string('service')->nullable();
            $table->string('category')->nullable();
            $table->string('budget')->nullable();
            $table->string('deadline')->nullable();
            $table->text('description')->nullable();
            $table->json('attachments')->nullable();
            $table->string('source')->default('needs-discovery');
            $table->json('answers')->nullable();
            $table->text('summary')->nullable();
            $table->json('recommended_services')->nullable();
            $table->string('status', 32)->default('NEW');
            $table->foreignId('assigned_to')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('crm_lead_id')->nullable()->constrained('crm_leads')->nullOnDelete();
            $table->foreignId('task_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->boolean('qualified')->default(false);
            $table->timestamp('qualified_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['email', 'phone']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('requirements');
    }
};
