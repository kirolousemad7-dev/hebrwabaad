<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meeting_participants', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_id')->constrained('meetings')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('role', 32)->default('attendee');
            $table->timestamps();

            $table->unique(['meeting_id', 'user_id'], 'meeting_participants_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_participants');
    }
};
