<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->unsignedTinyInteger('progress_percent')->default(0)->after('status');
            $table->unsignedInteger('sort_order')->default(0)->after('progress_percent');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropColumn(['progress_percent', 'sort_order']);
        });
    }
};
