<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('files', 'is_client_visible')) {
            return;
        }

        Schema::table('files', function (Blueprint $table): void {
            // Existing rows receive false via the column default — no backfill to true.
            $table->boolean('is_client_visible')->default(false);
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('files', 'is_client_visible')) {
            return;
        }

        Schema::table('files', function (Blueprint $table): void {
            $table->dropColumn('is_client_visible');
        });
    }
};
