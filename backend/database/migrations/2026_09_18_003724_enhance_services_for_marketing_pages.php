<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->string('subcategory')->nullable()->after('category');
            $table->string('hero_image')->nullable()->after('summary');
            $table->json('gallery')->nullable()->after('hero_image');
            $table->json('features')->nullable()->after('deliverables');
            $table->json('process_steps')->nullable()->after('features');
            $table->json('faq')->nullable()->after('process_steps');
            $table->json('tags')->nullable()->after('faq');
            $table->string('seo_title')->nullable()->after('checklist_template');
            $table->text('seo_description')->nullable()->after('seo_title');
            $table->string('og_title')->nullable()->after('seo_description');
            $table->text('og_description')->nullable()->after('og_title');
            $table->string('og_image')->nullable()->after('og_description');
            $table->string('canonical_url')->nullable()->after('og_image');
            $table->string('robots', 64)->nullable()->after('canonical_url');
        });

        Schema::create('service_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['service_id', 'supplier_id']);
        });

        Schema::create('service_supplier_product', function (Blueprint $table) {
            $table->id();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_product_id')->constrained('supplier_products')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['service_id', 'supplier_product_id'], 'service_product_unique');
        });

        Schema::create('project_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['project_id', 'service_id']);
        });

        Schema::create('commercial_quotation_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commercial_quotation_id')->constrained('commercial_quotations')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['commercial_quotation_id', 'service_id'], 'quotation_service_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('commercial_quotation_service');
        Schema::dropIfExists('project_service');
        Schema::dropIfExists('service_supplier_product');
        Schema::dropIfExists('service_supplier');

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn([
                'subcategory',
                'hero_image',
                'gallery',
                'features',
                'process_steps',
                'faq',
                'tags',
                'seo_title',
                'seo_description',
                'og_title',
                'og_description',
                'og_image',
                'canonical_url',
                'robots',
            ]);
        });
    }
};
