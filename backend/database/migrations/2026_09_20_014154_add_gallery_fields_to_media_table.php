<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->string('collection', 64)->default('default')->after('metadata');
            $table->unsignedInteger('sort_order')->default(0)->after('collection');
            $table->boolean('is_primary')->default(false)->after('sort_order');

            $table->index(['owner_type', 'owner_id', 'collection', 'sort_order'], 'media_owner_collection_order_idx');
            $table->index(['owner_type', 'owner_id', 'is_primary'], 'media_owner_primary_idx');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropIndex('media_owner_collection_order_idx');
            $table->dropIndex('media_owner_primary_idx');
            $table->dropColumn(['collection', 'sort_order', 'is_primary']);
        });
    }
};
