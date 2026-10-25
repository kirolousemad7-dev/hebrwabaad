<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oto_shipments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->restrictOnDelete();
            $table->string('oto_order_id', 64)->nullable()->unique();
            $table->string('oto_internal_id', 64)->nullable();
            $table->string('shipment_id', 64)->nullable()->unique();
            $table->string('delivery_option_id', 40)->nullable();
            $table->string('carrier', 120)->nullable();
            $table->string('tracking_number', 120)->nullable();
            $table->string('tracking_url', 2000)->nullable();
            $table->string('awb_url', 2000)->nullable();
            $table->decimal('shipping_cost', 12, 2)->nullable();
            $table->string('currency', 3)->default('SAR');
            $table->decimal('weight_kg', 8, 2)->nullable();
            $table->string('status', 64)->default('quoted');
            $table->timestamp('remote_order_created_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamp('last_event_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->string('recipient_name', 120)->nullable();
            $table->string('recipient_phone', 20)->nullable();
            $table->string('address', 300)->nullable();
            $table->string('city', 80)->nullable();
            $table->string('country', 2)->nullable();
            $table->json('quote_options')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('tracking_number');
        });

        Schema::create('oto_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type', 32);
            $table->string('oto_order_id', 64)->nullable();
            $table->string('idempotency_key', 64)->unique();
            $table->json('payload');
            $table->string('signature', 255)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['oto_order_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oto_webhook_events');
        Schema::dropIfExists('oto_shipments');
    }
};
