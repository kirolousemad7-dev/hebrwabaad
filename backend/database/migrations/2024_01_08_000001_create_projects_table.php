<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('account_manager_id')->constrained('users')->restrictOnDelete();
            $table->string('status')->default('PLANNING');
            $table->date('started_at')->nullable();
            $table->date('deadline')->nullable();
            $table->timestamps();

            $table->index(['account_manager_id', 'status']);
            $table->index(['customer_id', 'status']);
            $table->index('deadline');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
