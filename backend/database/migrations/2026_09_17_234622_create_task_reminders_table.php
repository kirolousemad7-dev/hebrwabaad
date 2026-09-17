<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('task_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('offset', 32)->nullable();
            $table->unsignedInteger('custom_minutes')->nullable();
            $table->timestamp('remind_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['remind_at', 'sent_at'], 'task_reminders_due_idx');
            $table->index(['task_id', 'offset'], 'task_reminders_task_offset_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('task_reminders');
    }
};
