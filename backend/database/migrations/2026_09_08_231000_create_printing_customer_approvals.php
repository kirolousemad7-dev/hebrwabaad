<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printing_customer_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('printing_request_id')->constrained('printing_requests')->cascadeOnDelete();
            $table->string('type', 20); // DESIGN|SIZE|FINAL
            $table->string('status', 20)->default('PENDING'); // PENDING|APPROVED|REJECTED
            $table->string('public_token_hash', 64)->unique();
            $table->string('title');
            $table->timestamp('decided_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['printing_request_id', 'status']);
            $table->index(['printing_request_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printing_customer_approvals');
    }
};
