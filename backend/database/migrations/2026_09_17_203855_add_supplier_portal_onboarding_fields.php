<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->json('locked_fields')->nullable()->after('internal_notes');
            $table->string('contact_person')->nullable()->after('display_name');
            $table->text('owner_change_request')->nullable()->after('review_notes');
        });
    }

    public function down(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn(['locked_fields', 'contact_person', 'owner_change_request']);
        });
    }
};
