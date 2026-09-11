<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sectors', function (Blueprint $table): void {
            $table->id();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->json('needs')->nullable();
            $table->string('cover_image')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_public')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->timestamps();
        });

        Schema::create('sector_service', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sector_id')->constrained('sectors')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['sector_id', 'service_id']);
        });

        Schema::create('sector_package', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sector_id')->constrained('sectors')->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['sector_id', 'package_id']);
        });

        Schema::create('sector_portfolio_item', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sector_id')->constrained('sectors')->cascadeOnDelete();
            $table->foreignId('portfolio_item_id')->constrained('portfolio_items')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['sector_id', 'portfolio_item_id']);
        });

        Schema::create('catalog_addons', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('summary');
            $table->text('description')->nullable();
            $table->string('pricing_mode', 24)->default('QUOTE');
            $table->decimal('price', 12, 2)->nullable();
            $table->unsignedInteger('percentage_bps')->nullable();
            $table->string('currency', 3)->default('SAR');
            $table->unsignedInteger('min_qty')->nullable();
            $table->unsignedInteger('max_qty')->nullable();
            $table->boolean('is_urgent')->default(false);
            $table->boolean('requires_capacity')->default(false);
            $table->boolean('capacity_available')->default(false);
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_public')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('addon_service', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('catalog_addon_id')->constrained('catalog_addons')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['catalog_addon_id', 'service_id']);
        });

        Schema::create('addon_package', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('catalog_addon_id')->constrained('catalog_addons')->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['catalog_addon_id', 'package_id']);
        });

        Schema::create('recommendation_goals', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name_ar');
            $table->text('explanation')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('recommendation_goal_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('recommendation_goal_id')->constrained('recommendation_goals')->cascadeOnDelete();
            $table->string('item_type', 24);
            $table->string('item_slug');
            $table->unsignedInteger('priority')->default(0);
            $table->string('reason_ar')->nullable();
            $table->unsignedInteger('quantity')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            // Explicit short name: auto name is exactly 64 chars (MariaDB limit).
            $table->index(
                ['recommendation_goal_id', 'item_type'],
                'rec_goal_items_goal_type_idx',
            );
            $table->index(['item_type', 'item_slug']);
        });

        Schema::create('event_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type');
            $table->date('event_date')->nullable();
            $table->string('city')->nullable();
            $table->unsignedInteger('attendance')->nullable();
            $table->string('venue')->nullable();
            $table->string('budget_range')->nullable();
            $table->string('buy_or_rent')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 32)->default('PENDING')->index();
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->foreignId('consultation_id')->nullable()->constrained('consultations')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->text('scope')->nullable()->after('description');
            $table->json('deliverables')->nullable()->after('scope');
            $table->unsignedInteger('revision_rounds')->nullable()->after('duration_days');
            $table->boolean('is_public')->default(true)->after('is_featured');
            $table->unsignedInteger('sort_order')->default(0)->after('is_public');
        });

        Schema::table('packages', function (Blueprint $table): void {
            if (! Schema::hasColumn('packages', 'is_public')) {
                $table->boolean('is_public')->default(true)->after('is_featured');
            }
        });

        Schema::table('portfolio_items', function (Blueprint $table): void {
            $table->string('brand_name')->nullable()->after('title');
            $table->text('challenge')->nullable()->after('description');
            $table->text('solution')->nullable()->after('challenge');
            $table->text('execution')->nullable()->after('solution');
            $table->json('deliverables')->nullable()->after('execution');
            $table->text('results')->nullable()->after('deliverables');
        });

        Schema::table('printing_requests', function (Blueprint $table): void {
            $table->foreignId('reordered_from_id')->nullable()->after('user_id')
                ->constrained('printing_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('printing_requests', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('reordered_from_id');
        });

        Schema::table('portfolio_items', function (Blueprint $table): void {
            $table->dropColumn(['brand_name', 'challenge', 'solution', 'execution', 'deliverables', 'results']);
        });

        Schema::table('packages', function (Blueprint $table): void {
            if (Schema::hasColumn('packages', 'is_public')) {
                $table->dropColumn('is_public');
            }
        });

        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn(['scope', 'deliverables', 'revision_rounds', 'is_public', 'sort_order']);
        });

        Schema::dropIfExists('event_requests');
        Schema::dropIfExists('recommendation_goal_items');
        Schema::dropIfExists('recommendation_goals');
        Schema::dropIfExists('addon_package');
        Schema::dropIfExists('addon_service');
        Schema::dropIfExists('catalog_addons');
        Schema::dropIfExists('sector_portfolio_item');
        Schema::dropIfExists('sector_package');
        Schema::dropIfExists('sector_service');
        Schema::dropIfExists('sectors');
    }
};
