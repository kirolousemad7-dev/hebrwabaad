<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->boolean('is_custom_package')->default(false)->after('package_tier_id');
            $table->boolean('requires_quote')->default(false)->after('is_custom_package');
        });

        Schema::create('order_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('pricing_mode', 32)->nullable();
            $table->decimal('unit_price', 12, 2)->nullable();
            $table->string('currency', 3)->default('SAR');
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['order_id', 'service_id']);
            $table->index(['order_id', 'sort_order']);
        });

        Schema::create('order_item_addons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_item_id')->constrained('order_items')->cascadeOnDelete();
            $table->foreignId('catalog_addon_id')->constrained('catalog_addons')->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['order_item_id', 'catalog_addon_id']);
        });

        Schema::create('order_addons', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('catalog_addon_id')->constrained('catalog_addons')->restrictOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();

            $table->unique(['order_id', 'catalog_addon_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_addons');
        Schema::dropIfExists('order_item_addons');
        Schema::dropIfExists('order_items');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn(['is_custom_package', 'requires_quote']);
        });
    }
};
