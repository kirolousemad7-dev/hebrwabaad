<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('printing_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('product_slug');
            $table->string('product_name');
            $table->decimal('width', 8, 2);
            $table->decimal('height', 8, 2);
            $table->string('dimension_unit', 8);
            $table->string('shape', 32);
            $table->string('material');
            $table->unsignedInteger('quantity');
            $table->string('printing_method', 32);
            $table->json('finishing');
            $table->string('file_path');
            $table->string('original_filename');
            $table->date('required_date');
            $table->text('notes')->nullable();
            $table->string('status', 32)->default('PENDING')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('printing_requests');
    }
};
