<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table): void {
            $table->text('audience')->nullable()->after('description');
            $table->json('deliverables')->nullable()->after('audience');
            $table->unsignedInteger('revision_rounds')->nullable()->after('duration_days');
            $table->string('pricing_mode', 24)->default('FIXED')->after('currency');
            $table->unsignedInteger('sort_order')->default(0)->after('is_featured');
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->string('pricing_mode', 24)->default('FIXED')->after('currency');
        });

        Schema::create('package_tiers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug', 32);
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('currency', 3)->default('SAR');
            $table->unsignedInteger('duration_days')->nullable();
            $table->unsignedInteger('revision_rounds')->nullable();
            $table->json('deliverables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['package_id', 'slug']);
            $table->index(['package_id', 'is_active']);
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->foreignId('package_tier_id')->nullable()->after('package_id')
                ->constrained('package_tiers')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('package_tier_id');
        });

        Schema::dropIfExists('package_tiers');

        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn('pricing_mode');
        });

        Schema::table('packages', function (Blueprint $table): void {
            $table->dropColumn(['audience', 'deliverables', 'revision_rounds', 'pricing_mode', 'sort_order']);
        });
    }
};
