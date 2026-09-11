<?php

namespace Tests\Feature;

use App\Enums\PrintingDeliveryMethod;
use App\Enums\PrintingDeliveryStatus;
use App\Enums\PrintingRequestStatus;
use App\Models\ManagedFile;
use App\Models\PrintingDelivery;
use App\Models\PrintingRequest;
use App\Models\User;
use App\Services\Delivery\DeliveryProviderManager;
use App\Services\Delivery\ManualDeliveryProvider;
use App\Services\Printing\PrintingDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintingDeliveryPhase8Test extends TestCase
{
    use RefreshDatabase;

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken($user->createToken('auth')->plainTextToken);
    }

    public function test_create_delivery_accepts_contact_and_scheduled_window(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create();
        $request = PrintingRequest::factory()->create([
            'user_id' => $customer->id,
            'status' => PrintingRequestStatus::ReadyForDelivery,
        ]);

        $response = $this->asUser($owner)
            ->postJson('/api/operations/printing/'.$request->id.'/deliveries', [
                'method' => PrintingDeliveryMethod::ManualDelivery->value,
                'recipient_name' => 'فرع الرياض',
                'contact_name' => 'أحمد',
                'contact_phone' => '0500000000',
                'scheduled_window' => '10:00-12:00',
                'notes' => 'يرجى الاتصال قبل الوصول',
            ])
            ->assertCreated();

        $response->assertJsonPath('data.contact_name', 'أحمد');
        $response->assertJsonPath('data.contact_phone', '0500000000');
        $response->assertJsonPath('data.scheduled_window', '10:00-12:00');
        $response->assertJsonPath('data.provider', DeliveryProviderManager::MANUAL);

        $delivery = PrintingDelivery::query()->findOrFail($response->json('data.id'));
        $this->assertSame('أحمد', $delivery->contact_name);
        $this->assertSame('0500000000', $delivery->contact_phone);
        $this->assertSame('10:00-12:00', $delivery->scheduled_window);
    }

    public function test_mark_delivered_can_attach_proof_file(): void
    {
        $owner = User::factory()->owner()->create();
        $customer = User::factory()->create();
        $request = PrintingRequest::factory()->create([
            'user_id' => $customer->id,
            'status' => PrintingRequestStatus::ReadyForDelivery,
        ]);
        $file = ManagedFile::factory()->create(['uploaded_by' => $owner->id]);
        $delivery = PrintingDelivery::factory()->create([
            'printing_request_id' => $request->id,
            'method' => PrintingDeliveryMethod::Pickup,
            'provider' => DeliveryProviderManager::MANUAL,
            'status' => PrintingDeliveryStatus::Pending,
            'created_by' => $owner->id,
        ]);

        $this->asUser($owner)
            ->postJson('/api/operations/printing-deliveries/'.$delivery->id.'/mark-delivered', [
                'notes' => 'تم التسليم',
                'proof_file_id' => $file->id,
                'received_by' => 'العميل',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', PrintingDeliveryStatus::Delivered->value)
            ->assertJsonPath('data.proof_file_id', $file->id);

        $fresh = $delivery->fresh();
        $this->assertSame($file->id, $fresh?->proof_file_id);
        $this->assertSame('العميل', $fresh?->metadata['received_by'] ?? null);
    }

    public function test_customer_tracking_includes_pickup_message_and_scheduled_window(): void
    {
        $customer = User::factory()->create();
        $request = PrintingRequest::factory()->create([
            'user_id' => $customer->id,
            'status' => PrintingRequestStatus::ReadyForDelivery,
            'delivery_method' => PrintingDeliveryMethod::Pickup->value,
        ]);
        PrintingDelivery::factory()->create([
            'printing_request_id' => $request->id,
            'method' => PrintingDeliveryMethod::Pickup,
            'provider' => DeliveryProviderManager::MANUAL,
            'status' => PrintingDeliveryStatus::Pending,
            'scheduled_window' => 'بعد العصر',
        ]);

        $payload = app(PrintingDeliveryService::class)
            ->customerSafePayloadForRequest($request->fresh() ?? $request);

        $this->assertSame('طلبكم جاهز للاستلام', $payload['pickup_ready_message'] ?? null);
        $this->assertSame('بعد العصر', $payload['scheduled_window'] ?? null);
    }

    public function test_delivery_providers_available_is_manual_only(): void
    {
        $manager = app(DeliveryProviderManager::class);

        $this->assertSame([ManualDeliveryProvider::KEY], $manager->available());
        $this->assertTrue($manager->has(DeliveryProviderManager::MANUAL));

        $owner = User::factory()->owner()->create();
        $this->asUser($owner)
            ->getJson('/api/operations/settings')
            ->assertOk()
            ->assertJsonPath('data.delivery_providers.available.0', 'manual');
    }
}
