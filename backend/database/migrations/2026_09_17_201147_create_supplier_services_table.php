<?php

use App\Enums\SupplierPricingModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_services', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('pricing_model', 32)->default(SupplierPricingModel::CustomQuote->value)->index();
            $table->decimal('minimum_price', 12, 2)->nullable();
            $table->decimal('maximum_price', 12, 2)->nullable();
            $table->string('currency', 8)->default('SAR');
            $table->string('delivery_time', 120)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['supplier_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_services');
    }
};
