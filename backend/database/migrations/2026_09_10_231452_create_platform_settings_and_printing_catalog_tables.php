<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });

        Schema::create('printing_product_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('printing_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('category_id')->nullable()->constrained('printing_product_categories')->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('short_description')->nullable();
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->string('pricing_mode', 20)->default('QUOTE');
            $table->decimal('starting_price', 12, 2)->nullable();
            $table->string('currency', 3)->default('SAR');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->boolean('allows_design_and_print')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_public', 'is_active', 'sort_order']);
        });

        Schema::create('printing_product_options', function (Blueprint $table): void {
            $table->id();
            $table->string('type', 40);
            $table->string('slug')->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['type', 'is_active']);
        });

        Schema::create('printing_product_option', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('printing_product_id')->constrained('printing_products')->cascadeOnDelete();
            $table->foreignId('printing_product_option_id')->constrained('printing_product_options')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['printing_product_id', 'printing_product_option_id'], 'printing_product_option_unique');
        });

        Schema::create('event_types', function (Blueprint $table): void {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name_ar');
            $table->string('name_en')->nullable();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_types');
        Schema::dropIfExists('printing_product_option');
        Schema::dropIfExists('printing_product_options');
        Schema::dropIfExists('printing_products');
        Schema::dropIfExists('printing_product_categories');
        Schema::dropIfExists('platform_settings');
    }
};
