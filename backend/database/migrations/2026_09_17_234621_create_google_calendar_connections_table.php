<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_calendar_connections', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('google_account_id')->nullable();
            $table->string('google_email')->nullable();
            $table->text('access_token_encrypted');
            $table->text('refresh_token_encrypted')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->string('scopes')->nullable();
            $table->string('calendar_id')->default('primary');
            $table->boolean('meet_enabled')->default(false);
            $table->boolean('sync_enabled')->default(true);
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_calendar_connections');
    }
};
