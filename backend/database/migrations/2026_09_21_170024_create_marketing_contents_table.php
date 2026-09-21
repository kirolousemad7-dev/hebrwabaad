<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketing_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('marketing_section_id')
                ->constrained('marketing_sections')
                ->cascadeOnDelete();
            $table->string('content_key', 96);
            $table->text('value_text')->nullable();
            $table->longText('value_html')->nullable();
            $table->foreignId('media_id')
                ->nullable()
                ->constrained('media')
                ->nullOnDelete();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['marketing_section_id', 'content_key'], 'marketing_contents_section_key_unique');
            $table->index(['is_enabled', 'sort_order']);
            $table->index('media_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marketing_contents');
    }
};
