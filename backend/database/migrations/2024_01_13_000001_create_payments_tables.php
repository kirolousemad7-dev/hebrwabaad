<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('order_id')->constrained('orders')->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3);
            $table->string('payment_method');
            $table->string('status');
            $table->string('provider');
            $table->string('provider_transaction_id')->nullable();
            $table->string('checkout_session_id')->nullable();
            $table->string('reference_number')->nullable();
            $table->string('payer_name')->nullable();
            $table->text('notes')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('provider_transaction_id');
            $table->index(['customer_id', 'status']);
            $table->index(['order_id', 'status']);
            $table->index(['status', 'payment_method']);
            $table->index('created_at');
        });

        Schema::create('payment_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('card_enabled')->default(true);
            $table->boolean('instapay_enabled')->default(true);
            $table->string('instapay_account_name')->nullable();
            $table->string('instapay_bank_name')->nullable();
            $table->string('instapay_account_number')->nullable();
            $table->text('instapay_instructions')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
        Schema::dropIfExists('payment_settings');
    }
};
