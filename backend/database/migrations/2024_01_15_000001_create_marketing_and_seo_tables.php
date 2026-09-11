<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_pages', function (Blueprint $table): void {
            $table->id();
            $table->string('page_key', 64)->unique();
            $table->string('title', 70)->nullable();
            $table->string('description', 320)->nullable();
            $table->string('keywords', 255)->nullable();
            $table->string('canonical_url', 2048)->nullable();
            $table->string('og_title', 70)->nullable();
            $table->string('og_description', 320)->nullable();
            $table->string('og_image', 2048)->nullable();
            $table->string('twitter_title', 70)->nullable();
            $table->string('twitter_description', 320)->nullable();
            $table->string('twitter_image', 2048)->nullable();
            $table->string('robots', 64)->default('index,follow');
            $table->json('schema_json')->nullable();
            $table->timestamps();
        });

        Schema::create('portfolio_items', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('category', 32);
            $table->text('description')->nullable();
            $table->json('tags')->nullable();
            $table->string('image_url', 2048);
            $table->boolean('is_sample')->default(true);
            $table->boolean('is_published')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
            $table->index('category');
        });

        Schema::create('testimonials', function (Blueprint $table): void {
            $table->id();
            $table->string('author_name');
            $table->string('author_role')->nullable();
            $table->text('quote');
            $table->boolean('is_published')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_published', 'sort_order']);
        });

        Schema::create('contact_inquiries', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 40)->nullable();
            $table->text('message');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_inquiries');
        Schema::dropIfExists('testimonials');
        Schema::dropIfExists('portfolio_items');
        Schema::dropIfExists('seo_pages');
    }
};
