<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->timestamp('start_at')->nullable()->after('deadline');
            $table->timestamp('due_at')->nullable()->after('start_at');
            $table->string('timezone', 64)->nullable()->after('due_at');
            $table->string('location')->nullable()->after('timezone');
            $table->boolean('google_meet_enabled')->default(false)->after('location');
            $table->string('google_event_id')->nullable()->after('google_meet_enabled');
            $table->string('google_calendar_id')->nullable()->after('google_event_id');
            $table->string('google_sync_status', 32)->default('NONE')->after('google_calendar_id');
            $table->timestamp('google_synced_at')->nullable()->after('google_sync_status');
            $table->string('google_sync_error')->nullable()->after('google_synced_at');
            $table->string('google_html_link', 1024)->nullable()->after('google_sync_error');
            $table->string('google_etag')->nullable()->after('google_html_link');
            $table->unsignedInteger('google_sync_version')->default(0)->after('google_etag');
            $table->boolean('google_sync_enabled')->default(false)->after('google_sync_version');

            $table->index(['google_sync_status', 'google_sync_enabled'], 'tasks_google_sync_idx');
            $table->index('google_event_id', 'tasks_google_event_id_idx');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table): void {
            $table->dropIndex('tasks_google_sync_idx');
            $table->dropIndex('tasks_google_event_id_idx');
            $table->dropColumn([
                'start_at',
                'due_at',
                'timezone',
                'location',
                'google_meet_enabled',
                'google_event_id',
                'google_calendar_id',
                'google_sync_status',
                'google_synced_at',
                'google_sync_error',
                'google_html_link',
                'google_etag',
                'google_sync_version',
                'google_sync_enabled',
            ]);
        });
    }
};
