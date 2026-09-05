<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_settings', function (Blueprint $table): void {
            $table->string('instapay_handle')->nullable()->after('instapay_account_number');
            $table->string('instapay_phone', 40)->nullable()->after('instapay_handle');
            $table->text('instapay_notes')->nullable()->after('instapay_instructions');

            $table->boolean('bank_transfer_enabled')->default(false)->after('instapay_notes');
            $table->string('bank_name')->nullable()->after('bank_transfer_enabled');
            $table->string('bank_account_name')->nullable()->after('bank_name');
            $table->string('bank_account_number')->nullable()->after('bank_account_name');
            $table->string('bank_iban')->nullable()->after('bank_account_number');
            $table->string('bank_swift', 32)->nullable()->after('bank_iban');
            $table->string('bank_branch')->nullable()->after('bank_swift');
            $table->text('bank_instructions')->nullable()->after('bank_branch');
            $table->text('bank_notes')->nullable()->after('bank_instructions');
        });
    }

    public function down(): void
    {
        Schema::table('payment_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'instapay_handle',
                'instapay_phone',
                'instapay_notes',
                'bank_transfer_enabled',
                'bank_name',
                'bank_account_name',
                'bank_account_number',
                'bank_iban',
                'bank_swift',
                'bank_branch',
                'bank_instructions',
                'bank_notes',
            ]);
        });
    }
};
