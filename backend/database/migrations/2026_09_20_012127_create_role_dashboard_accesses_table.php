<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_dashboard_access', function (Blueprint $table): void {
            $table->id();
            $table->string('role', 32);
            $table->string('module_key', 80);
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->unique(['role', 'module_key']);
            $table->index('role');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_dashboard_access');
    }
};
