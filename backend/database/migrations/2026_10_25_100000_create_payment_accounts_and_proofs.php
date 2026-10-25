<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('method', 32);
            $table->string('account_holder_name');
            $table->string('instapay_address')->nullable();
            $table->string('mobile_number', 40)->nullable();
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('iban')->nullable();
            $table->string('branch_name')->nullable();
            $table->text('instructions');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['method', 'is_active', 'archived_at']);
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->foreignId('payment_account_id')->nullable()->after('payment_method')->constrained('payment_accounts')->nullOnDelete();
            $table->string('proof_disk')->nullable()->after('notes');
            $table->string('proof_path')->nullable()->after('proof_disk');
            $table->string('proof_original_name')->nullable()->after('proof_path');
            $table->string('proof_mime', 80)->nullable()->after('proof_original_name');
            $table->unsignedInteger('proof_size')->nullable()->after('proof_mime');
        });

        Schema::create('payment_verification_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['payment_id', 'id']);
        });

        if (Schema::hasTable('payment_settings')) {
            $settings = DB::table('payment_settings')->orderBy('id')->first();
            if ($settings !== null) {
                $now = now();
                if (filled($settings->instapay_account_name ?? null) && (filled($settings->instapay_handle ?? null) || filled($settings->instapay_account_number ?? null))) {
                    DB::table('payment_accounts')->insert([
                        'method' => 'INSTAPAY',
                        'account_holder_name' => $settings->instapay_account_name,
                        'instapay_address' => $settings->instapay_handle ?: $settings->instapay_account_number,
                        'mobile_number' => $settings->instapay_phone ?? null,
                        'instructions' => $settings->instapay_instructions ?: 'حوّل المبلغ ثم ارفع صورة الإثبات.',
                        'is_active' => (bool) ($settings->instapay_enabled ?? false),
                        'is_default' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
                if (filled($settings->bank_name ?? null) && filled($settings->bank_account_name ?? null)) {
                    DB::table('payment_accounts')->insert([
                        'method' => 'BANK_TRANSFER',
                        'account_holder_name' => $settings->bank_account_name,
                        'bank_name' => $settings->bank_name,
                        'account_number' => $settings->bank_account_number ?? null,
                        'iban' => $settings->bank_iban ?? null,
                        'branch_name' => $settings->bank_branch ?? null,
                        'instructions' => $settings->bank_instructions ?: 'حوّل المبلغ ثم ارفع صورة الإثبات.',
                        'is_active' => (bool) ($settings->bank_transfer_enabled ?? false),
                        'is_default' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_verification_events');
        Schema::table('payments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('payment_account_id');
            $table->dropColumn(['proof_disk', 'proof_path', 'proof_original_name', 'proof_mime', 'proof_size']);
        });
        Schema::dropIfExists('payment_accounts');
    }
};
