<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->string('disk', 32);
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type', 127);
            $table->string('extension', 32)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('checksum', 64)->nullable();
            $table->string('visibility', 32);
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->nullableMorphs('owner');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['visibility', 'created_at']);
            $table->index(['uploaded_by', 'created_at']);
            $table->index('checksum');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};
