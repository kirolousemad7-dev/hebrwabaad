<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->string('button_color', 16)->nullable();
            $table->json('excluded_service_names')->nullable();
        });

        Schema::table('services', function (Blueprint $table) {
            $table->string('button_color', 16)->nullable();
            $table->string('icon_key', 64)->nullable();
        });

        Schema::table('portfolio_items', function (Blueprint $table) {
            $table->string('catalog_pdf_url', 2048)->nullable();
            $table->string('profile_pdf_url', 2048)->nullable();
        });

        Schema::table('supplier_products', function (Blueprint $table) {
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->string('button_color', 16)->nullable();
        });

        Schema::table('printing_products', function (Blueprint $table) {
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->string('button_color', 16)->nullable();
        });

        $this->seedOptionalMarketingKeys();
    }

    public function down(): void
    {
        Schema::table('printing_products', function (Blueprint $table) {
            $table->dropColumn(['compare_at_price', 'button_color']);
        });

        Schema::table('supplier_products', function (Blueprint $table) {
            $table->dropColumn(['compare_at_price', 'button_color']);
        });

        Schema::table('portfolio_items', function (Blueprint $table) {
            $table->dropColumn(['catalog_pdf_url', 'profile_pdf_url']);
        });

        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn(['button_color', 'icon_key']);
        });

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['button_color', 'excluded_service_names']);
        });
    }

    private function seedOptionalMarketingKeys(): void
    {
        if (! Schema::hasTable('marketing_sections') || ! Schema::hasTable('marketing_contents')) {
            return;
        }

        $now = now();
        $keys = [
            'hero' => [
                ['content_key' => 'visual_aspect', 'value_text' => '4:3', 'sort_order' => 20],
                ['content_key' => 'visual_video', 'value_text' => null, 'sort_order' => 21],
                ['content_key' => 'visual_width', 'value_text' => null, 'sort_order' => 22],
                ['content_key' => 'visual_height', 'value_text' => null, 'sort_order' => 23],
            ],
            'services' => [
                ['content_key' => 'button_color', 'value_text' => null, 'sort_order' => 30],
                ['content_key' => 'icon_strategy', 'value_text' => 'design', 'sort_order' => 31],
                ['content_key' => 'icon_branding', 'value_text' => 'design', 'sort_order' => 32],
                ['content_key' => 'icon_digital', 'value_text' => 'speed', 'sort_order' => 33],
                ['content_key' => 'icon_ecommerce', 'value_text' => 'delivery', 'sort_order' => 34],
                ['content_key' => 'icon_printing', 'value_text' => 'print', 'sort_order' => 35],
                ['content_key' => 'icon_events', 'value_text' => 'warranty', 'sort_order' => 36],
            ],
            'process' => [
                ['content_key' => 'step_1_description', 'value_text' => 'نفهم وضعك الحالي.', 'sort_order' => 40],
                ['content_key' => 'step_2_description', 'value_text' => 'نرتب ما يُنفَّذ أولًا.', 'sort_order' => 41],
                ['content_key' => 'step_3_description', 'value_text' => 'خدمة أو باقة مناسبة.', 'sort_order' => 42],
                ['content_key' => 'step_4_description', 'value_text' => 'عرض واضح ثم بدء العمل.', 'sort_order' => 43],
                ['content_key' => 'step_5_description', 'value_text' => 'تسليم وقياس مختصر.', 'sort_order' => 44],
            ],
        ];

        foreach ($keys as $sectionKey => $rows) {
            $sectionId = DB::table('marketing_sections')->where('key', $sectionKey)->value('id');
            if (! $sectionId) {
                continue;
            }

            foreach ($rows as $row) {
                $exists = DB::table('marketing_contents')
                    ->where('marketing_section_id', $sectionId)
                    ->where('content_key', $row['content_key'])
                    ->exists();

                if ($exists) {
                    continue;
                }

                DB::table('marketing_contents')->insert([
                    'marketing_section_id' => $sectionId,
                    'content_key' => $row['content_key'],
                    'value_text' => $row['value_text'],
                    'sort_order' => $row['sort_order'],
                    'is_enabled' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }
};
