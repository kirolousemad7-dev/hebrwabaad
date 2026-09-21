<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->longText('content');
            $table->string('page_type', 32)->default('GENERAL');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->string('meta_keywords')->nullable();
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_image')->nullable();
            $table->boolean('is_published')->default(false);
            $table->boolean('show_in_footer')->default(false);
            $table->string('footer_group')->nullable();
            $table->unsignedInteger('footer_order')->default(0);
            $table->timestamps();

            $table->index(
                ['is_published', 'show_in_footer', 'footer_group', 'footer_order'],
                'cms_pages_footer_visibility_index'
            );
            $table->index(['page_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_pages');
    }
};
