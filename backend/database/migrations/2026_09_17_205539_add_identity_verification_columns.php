<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('email_verification_sent_at')->nullable()->after('email_verified_at');
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->timestamp('email_verified_at')->nullable()->after('verification_status');
            $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
        });

        Schema::table('supplier_login_otps', function (Blueprint $table): void {
            $table->timestamp('sent_at')->nullable()->after('expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('supplier_login_otps', function (Blueprint $table): void {
            $table->dropColumn('sent_at');
        });

        Schema::table('suppliers', function (Blueprint $table): void {
            $table->dropColumn(['email_verified_at', 'phone_verified_at']);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('email_verification_sent_at');
        });
    }
};
