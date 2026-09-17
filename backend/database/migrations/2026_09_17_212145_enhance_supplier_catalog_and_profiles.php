<?php

use App\Enums\SupplierVisibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppliers', function (Blueprint $table): void {
            $table->string('visibility', 20)->default(SupplierVisibility::Private->value)->after('is_published');
            $table->string('availability', 40)->nullable()->after('visibility');
            $table->string('delivery_time', 120)->nullable()->after('availability');
            $table->json('service_areas')->nullable()->after('delivery_time');
            $table->json('certifications')->nullable()->after('service_areas');
        });

        // Preserve existing public catalog entries when introducing supplier visibility.
        DB::table('suppliers')
            ->where('is_published', true)
            ->update(['visibility' => SupplierVisibility::Public->value]);

        Schema::table('supplier_categories', function (Blueprint $table): void {
            $table->foreignId('parent_id')->nullable()->after('id')->constrained('supplier_categories')->nullOnDelete();
            $table->string('icon', 255)->nullable()->after('description');
            $table->string('seo_title', 70)->nullable()->after('sort_order');
            $table->string('seo_description', 320)->nullable()->after('seo_title');
        });

        Schema::table('supplier_services', function (Blueprint $table): void {
            $table->string('category', 120)->nullable()->after('name');
            $table->string('service_area', 255)->nullable()->after('delivery_time');
            $table->string('visibility', 20)->default(SupplierVisibility::Internal->value)->after('is_active');
            $table->json('attachments')->nullable()->after('notes');
            $table->softDeletes();
        });

        Schema::table('supplier_portfolio_items', function (Blueprint $table): void {
            $table->string('client_type', 120)->nullable()->after('category');
            $table->json('documents')->nullable()->after('videos');
        });

        Schema::create('taggables', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
            $table->morphs('taggable');
            $table->timestamps();

            $table->unique(['tag_id', 'taggable_type', 'taggable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taggables');

        Schema::table('supplier_portfolio_items', function (Blueprint $table): void {
            $table->dropColumn(['client_type', 'documents']);
        });

        Schema::table('supplier_services', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropColumn(['category', 'service_area', 'visibility', 'attachments']);
        });

        Schema::table('supplier_categories', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['icon', 'seo_title', 'seo_description']);
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn(['visibility', 'availability', 'delivery_time', 'service_areas', 'certifications']);
        });
    }
};
