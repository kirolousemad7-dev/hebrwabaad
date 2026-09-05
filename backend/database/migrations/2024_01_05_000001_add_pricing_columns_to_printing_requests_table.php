<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('printing_requests', function (Blueprint $table) {
            $table->string('pricing_type', 32)->nullable()->after('status')->index();
            $table->decimal('estimated_price', 10, 2)->nullable()->after('pricing_type');
            $table->decimal('quoted_price', 10, 2)->nullable()->after('estimated_price');
            $table->text('pricing_notes')->nullable()->after('quoted_price');
            $table->timestamp('quoted_at')->nullable()->after('pricing_notes');
            $table->foreignId('quoted_by')->nullable()->after('quoted_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('printing_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quoted_by');
            $table->dropColumn([
                'pricing_type',
                'estimated_price',
                'quoted_price',
                'pricing_notes',
                'quoted_at',
            ]);
        });
    }
};
