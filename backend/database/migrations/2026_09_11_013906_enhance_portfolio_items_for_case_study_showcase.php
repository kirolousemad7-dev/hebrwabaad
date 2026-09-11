<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portfolio_items', function (Blueprint $table) {
            $table->string('slug', 160)->nullable()->after('title');
            $table->string('short_description', 500)->nullable()->after('description');
            $table->string('project_url', 2048)->nullable()->after('image_url');
            $table->string('video_url', 2048)->nullable()->after('project_url');
            $table->string('primary_media_type', 32)->nullable()->after('video_url');
            $table->boolean('is_featured')->default(false)->after('is_published');
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->unique('slug');
            $table->index(['is_published', 'is_featured', 'sort_order']);
        });

        Schema::create('portfolio_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_item_id')->constrained('portfolio_items')->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('title')->nullable();
            $table->string('caption', 1000)->nullable();
            $table->string('url', 2048)->nullable();
            $table->uuid('content_media_id')->nullable();
            $table->uuid('thumbnail_media_id')->nullable();
            $table->string('thumbnail_url', 2048)->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_public')->default(true);
            $table->timestamps();

            $table->index(['portfolio_item_id', 'display_order']);
        });

        Schema::create('portfolio_item_service', function (Blueprint $table) {
            $table->id();
            $table->foreignId('portfolio_item_id')->constrained('portfolio_items')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['portfolio_item_id', 'service_id']);
        });

        $this->backfillSlugs();
    }

    public function down(): void
    {
        Schema::dropIfExists('portfolio_item_service');
        Schema::dropIfExists('portfolio_media');

        Schema::table('portfolio_items', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropIndex(['is_published', 'is_featured', 'sort_order']);
            $table->dropConstrainedForeignId('package_id');
            $table->dropColumn([
                'slug',
                'short_description',
                'project_url',
                'video_url',
                'primary_media_type',
                'is_featured',
            ]);
        });
    }

    private function backfillSlugs(): void
    {
        $rows = DB::table('portfolio_items')->orderBy('id')->get(['id', 'title', 'slug']);
        $used = [];

        foreach ($rows as $row) {
            if (is_string($row->slug) && $row->slug !== '') {
                $used[$row->slug] = true;

                continue;
            }

            $base = Str::slug((string) $row->title);
            if ($base === '') {
                $base = Str::slug((string) $row->title, '-', null);
            }
            if ($base === '') {
                $base = 'portfolio-'.$row->id;
            }

            $slug = $base;
            $suffix = 2;
            while (isset($used[$slug])) {
                $slug = $base.'-'.$suffix;
                $suffix++;
            }

            $used[$slug] = true;
            DB::table('portfolio_items')->where('id', $row->id)->update(['slug' => $slug]);
        }
    }
};
