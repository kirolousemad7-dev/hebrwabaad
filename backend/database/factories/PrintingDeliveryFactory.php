<?php

namespace Database\Factories;

use App\Enums\PrintingDeliveryMethod;
use App\Enums\PrintingDeliveryStatus;
use App\Models\PrintingDelivery;
use App\Models\PrintingRequest;
use App\Services\Delivery\DeliveryProviderManager;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PrintingDelivery>
 */
class PrintingDeliveryFactory extends Factory
{
    protected $model = PrintingDelivery::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'printing_request_id' => PrintingRequest::factory(),
            'method' => PrintingDeliveryMethod::Pickup,
            'provider' => DeliveryProviderManager::MANUAL,
            'status' => PrintingDeliveryStatus::Pending,
            'external_reference' => null,
            'tracking_url' => null,
            'recipient_name' => fake()->name(),
            'contact_name' => null,
            'contact_phone' => null,
            'notes' => null,
            'scheduled_at' => null,
            'scheduled_window' => null,
            'delivered_at' => null,
            'proof_file_id' => null,
            'metadata' => null,
            'created_by' => null,
        ];
    }
}
