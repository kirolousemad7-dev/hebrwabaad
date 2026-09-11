<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_items', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 40);
            $table->string('status', 40)->default('SCHEDULED');
            $table->string('priority', 20)->default('MEDIUM');
            $table->string('visibility', 20)->default('PARTICIPANTS');
            $table->string('source', 40)->default('MANUAL');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->boolean('all_day')->default(false);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->string('related_type', 60)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->string('related_label')->nullable();
            $table->string('related_href')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['starts_at', 'ends_at']);
            $table->index(['status', 'type']);
            $table->index(['created_by', 'starts_at']);
            $table->index(['related_type', 'related_id']);
            $table->index(['source', 'starts_at']);
        });

        Schema::create('calendar_item_assignees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('calendar_item_id')->constrained('calendar_items')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['calendar_item_id', 'user_id']);
            $table->index('user_id');
        });

        Schema::create('calendar_reminders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('calendar_item_id')->constrained('calendar_items')->cascadeOnDelete();
            $table->string('offset', 40);
            $table->timestamp('remind_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['remind_at', 'sent_at']);
            $table->index('calendar_item_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_reminders');
        Schema::dropIfExists('calendar_item_assignees');
        Schema::dropIfExists('calendar_items');
    }
};
