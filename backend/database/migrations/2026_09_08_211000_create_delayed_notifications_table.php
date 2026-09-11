<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delayed_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('notification_class');
            $table->json('payload');
            $table->string('category', 40);
            $table->timestamp('deliver_after');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->index(['deliver_after', 'delivered_at']);
            $table->index(['user_id', 'delivered_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delayed_notifications');
    }
};
