<?php

use App\Enums\ContentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_media', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('disk', 32)->default('local');
            $table->string('path');
            $table->string('thumbnail_path')->nullable();
            $table->string('original_name');
            $table->string('mime_type', 127);
            $table->string('extension', 16);
            $table->unsignedBigInteger('size');
            $table->unsignedInteger('width')->nullable();
            $table->unsignedInteger('height')->nullable();
            $table->nullableMorphs('attachable');
            $table->string('collection', 32)->default('gallery');
            $table->timestamps();
        });

        Schema::create('work_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('category', 32);
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->uuid('cover_media_id')->nullable();
            $table->json('gallery_media_ids')->nullable();
            $table->json('tags')->nullable();
            $table->json('tools')->nullable();
            $table->string('project_url', 2048)->nullable();
            $table->string('video_url', 2048)->nullable();
            $table->string('client_label')->nullable();
            $table->text('employee_notes')->nullable();
            $table->string('status', 32)->default(ContentStatus::Draft->value)->index();
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_portfolio_item_id')->nullable()->constrained('portfolio_items')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('cover_media_id')->references('id')->on('content_media')->nullOnDelete();
            $table->index(['user_id', 'status']);
            $table->index('category');
        });

        Schema::table('portfolio_items', function (Blueprint $table): void {
            $table->foreignId('work_submission_id')->nullable()->unique()->after('id')->constrained('work_submissions')->nullOnDelete();
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('email')->nullable();
            $table->string('phone', 40)->nullable();
            $table->string('website', 2048)->nullable();
            $table->string('address')->nullable();
            $table->string('cover_image', 2048)->nullable();
            $table->string('category')->nullable()->index();
            $table->json('brand_colors')->nullable();
            $table->text('brand_description')->nullable();
            $table->unsignedTinyInteger('years_experience')->nullable();
            $table->string('min_order_info')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_published')->default(true)->index();
            $table->string('profile_status', 32)->default(ContentStatus::Published->value)->index();
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->string('seo_title', 70)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->string('og_title', 70)->nullable();
            $table->string('og_description', 320)->nullable();
            $table->string('og_image', 2048)->nullable();
            $table->string('canonical_url', 2048)->nullable();
            $table->string('robots', 64)->nullable();
            $table->index(['is_active', 'is_published']);
        });

        Schema::table('supplier_portfolio_items', function (Blueprint $table): void {
            $table->json('gallery')->nullable();
            $table->json('tags')->nullable();
            $table->string('external_url', 2048)->nullable();
            $table->boolean('is_featured')->default(false);
            $table->string('status', 32)->default(ContentStatus::Published->value)->index();
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->softDeletes();
            $table->index(['supplier_id', 'status']);
        });

        Schema::create('supplier_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('short_description')->nullable();
            $table->text('description')->nullable();
            $table->json('images')->nullable();
            $table->string('category')->nullable();
            $table->json('specifications')->nullable();
            $table->json('variants')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('currency', 8)->default('SAR');
            $table->boolean('contact_for_price')->default(true);
            $table->string('availability', 32)->default('CONTACT');
            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status', 32)->default(ContentStatus::Draft->value)->index();
            $table->text('review_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->string('seo_title', 70)->nullable();
            $table->string('seo_description', 320)->nullable();
            $table->string('og_title', 70)->nullable();
            $table->string('og_description', 320)->nullable();
            $table->string('og_image', 2048)->nullable();
            $table->string('canonical_url', 2048)->nullable();
            $table->string('robots', 64)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['supplier_id', 'slug']);
            $table->index(['supplier_id', 'status']);
            $table->index(['status', 'is_featured']);
        });

        Schema::create('supplier_profile_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->json('payload');
            $table->string('status', 32)->default(ContentStatus::Draft->value)->index();
            $table->text('review_notes')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'status']);
        });

        Schema::create('content_reviews', function (Blueprint $table): void {
            $table->id();
            $table->morphs('subject');
            $table->foreignId('actor_id')->constrained('users')->cascadeOnDelete();
            $table->string('action', 32);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['actor_id', 'created_at']);
        });

        $this->backfillExistingCatalog();
    }

    public function down(): void
    {
        Schema::dropIfExists('content_reviews');
        Schema::dropIfExists('supplier_profile_versions');
        Schema::dropIfExists('supplier_products');

        Schema::table('supplier_portfolio_items', function (Blueprint $table): void {
            $table->dropSoftDeletes();
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'gallery',
                'tags',
                'external_url',
                'is_featured',
                'status',
                'review_notes',
                'reviewed_at',
                'published_at',
                'submitted_at',
            ]);
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('user_id');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropColumn([
                'email',
                'phone',
                'website',
                'address',
                'cover_image',
                'category',
                'brand_colors',
                'brand_description',
                'years_experience',
                'min_order_info',
                'sort_order',
                'is_published',
                'profile_status',
                'review_notes',
                'reviewed_at',
                'published_at',
                'seo_title',
                'seo_description',
                'og_title',
                'og_description',
                'og_image',
                'canonical_url',
                'robots',
            ]);
        });

        Schema::table('portfolio_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('work_submission_id');
        });

        Schema::dropIfExists('work_submissions');
        Schema::dropIfExists('content_media');
    }

    private function backfillExistingCatalog(): void
    {
        $published = ContentStatus::Published->value;
        $archived = ContentStatus::Archived->value;

        foreach (DB::table('suppliers')->get() as $supplier) {
            $specialties = json_decode((string) $supplier->specialties, true);
            $category = is_array($specialties) && isset($specialties[0]) && is_string($specialties[0])
                ? $specialties[0]
                : 'الطباعة التجارية';

            DB::table('suppliers')->where('id', $supplier->id)->update([
                'is_published' => (bool) $supplier->is_active,
                'profile_status' => $published,
                'published_at' => now(),
                'category' => $category,
            ]);
        }

        DB::table('supplier_portfolio_items')->where('is_active', true)->update([
            'status' => $published,
            'published_at' => now(),
        ]);

        DB::table('supplier_portfolio_items')->where('is_active', false)->update([
            'status' => $archived,
        ]);
    }
};
