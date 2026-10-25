<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OtoShipment;
use App\Models\OtoWebhookEvent;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use App\Services\Shipping\OtoSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OtoShippingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::preventStrayRequests();
        $this->enableOto();
    }

    public function test_disabled_integration_does_not_call_oto(): void
    {
        config(['oto.enabled' => false]);
        ['owner' => $owner, 'order' => $order] = $this->paidOrder();

        $this->asUser($owner)
            ->postJson($this->quoteUrl($order), $this->recipient())
            ->assertStatus(503)
            ->assertJsonPath('message', 'تكامل الشحن غير مفعّل.');

        Http::assertNothingSent();
        $this->assertDatabaseCount('oto_shipments', 0);
    }

    public function test_owner_quotes_maps_items_and_does_not_send_the_sample_order_code(): void
    {
        ['owner' => $owner, 'order' => $order] = $this->paidOrder(withItem: true);
        $this->fakeOto();

        $this->asUser($owner)
            ->postJson($this->quoteUrl($order), $this->recipient())
            ->assertOk()
            ->assertJsonPath('data.quote_options.0.delivery_option_id', '2828')
            ->assertJsonPath('data.quote_options.0.carrier', 'SMSA')
            ->assertJsonPath('data.status', 'quoted');

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), 'checkOTODeliveryFee')) {
                return false;
            }

            return $request['destinationCity'] === 'Riyadh'
                && $request['originCity'] === 'Riyadh'
                && (float) $request['weight'] === 1.5
                && ! array_key_exists('orderId', $request->data())
                && ! str_contains((string) $request->body(), '2001');
        });
        $this->assertSame($order->reference, OtoShipment::query()->first()?->oto_order_id);
    }

    public function test_create_order_and_shipment_are_not_repeated(): void
    {
        ['owner' => $owner, 'customer' => $customer, 'order' => $order] = $this->paidOrder(withItem: true);
        $this->fakeOto();

        $this->asUser($owner)->postJson($this->quoteUrl($order), $this->recipient())->assertOk();
        $this->asUser($owner)->postJson($this->orderUrl($order), $this->recipient())->assertOk()
            ->assertJsonPath('data.oto_order_id', $order->reference)
            ->assertJsonPath('data.remote_order_created', true);

        $this->asUser($owner)->postJson($this->orderUrl($order), $this->recipient())->assertOk();

        $this->asUser($owner)
            ->postJson($this->shipmentUrl($order), ['delivery_option_id' => '2828'])
            ->assertOk()
            ->assertJsonPath('data.shipment_id', 'SHP-1')
            ->assertJsonPath('data.tracking_number', 'TRK1')
            ->assertJsonPath('data.awb_url', 'https://labels.example/awb.pdf')
            ->assertJsonPath('data.carrier', 'SMSA');

        $this->asUser($owner)
            ->postJson($this->shipmentUrl($order), ['delivery_option_id' => '2828'])
            ->assertOk()
            ->assertJsonPath('data.shipment_id', 'SHP-1');

        $this->assertSame(1, $this->sentCount('createOrder'));
        $this->assertSame(1, $this->sentCount('createShipment'));
        $this->assertSame(1, $this->sentCount('refreshToken'));

        Http::assertSent(function ($request) use ($order) {
            if (! str_contains($request->url(), 'createOrder')) {
                return false;
            }

            $data = $request->data();

            return $data['orderId'] === $order->reference
                && $data['orderId'] !== '2001'
                && $data['createShipment'] === false
                && $data['payment_method'] === 'paid'
                && $data['amount_due'] === 0
                && $data['pickupLocationCode'] === 'wh_test'
                && $data['items'][0]['name'] === 'تصميم شعار'
                && $data['items'][0]['quantity'] === 2
                && ! array_key_exists('refresh_token', $data);
        });

        foreach (Http::recorded() as [$request]) {
            if (! str_contains($request->url(), 'refreshToken')) {
                $this->assertStringNotContainsString('test-refresh-token', (string) $request->body());
                $this->assertStringNotContainsString('test-refresh-token', implode(' ', $request->header('Authorization')));
            }
        }

        $shipment = OtoShipment::query()->first();
        $this->assertNotNull($shipment);
        $this->assertSame('SHP-1', $shipment->shipment_id);
        $this->assertSame('25.00', $shipment->shipping_cost);
        $this->assertSame(PaymentStatus::Paid, $order->payments()->first()?->status);
        $this->assertSame(OrderStatus::Confirmed, $order->fresh()?->status);

        $this->asUser($customer)
            ->getJson('/api/customer/orders/'.$order->id)
            ->assertOk()
            ->assertJsonPath('data.shipment.tracking_number', 'TRK1')
            ->assertJsonMissingPath('data.shipment.awb_url')
            ->assertJsonMissingPath('data.shipment.error_message');

        $this->asUser($owner)
            ->getJson('/api/admin/orders/'.$order->id.'/oto/label')
            ->assertOk()
            ->assertJsonPath('data.awb_url', 'https://labels.example/awb.pdf');

        $this->assertSame(1, $this->sentCount('print/'));
    }

    public function test_existing_remote_order_is_reused_instead_of_creating_another(): void
    {
        ['owner' => $owner, 'order' => $order] = $this->paidOrder();
        Http::fake([
            '*refreshToken*' => Http::response(['access_token' => 'test-access', 'expires_in' => 3600]),
            '*orderDetails*' => Http::response(['success' => true, 'otoId' => 9001, 'orderId' => $order->reference]),
            '*createOrder*' => Http::response(['success' => true, 'otoId' => 1]),
        ]);

        $this->asUser($owner)->postJson($this->orderUrl($order), $this->recipient())->assertOk()
            ->assertJsonPath('data.remote_order_created', true);

        $this->assertSame(0, $this->sentCount('createOrder'));
        $this->assertSame('9001', OtoShipment::query()->first()?->oto_internal_id);
    }

    public function test_unverified_and_unpaid_orders_are_not_shipped(): void
    {
        $owner = User::factory()->owner()->create();
        $pending = $this->paidOrder(owner: $owner);
        $pending['order']->payments()->update(['status' => PaymentStatus::PendingVerification]);

        $unpaid = $this->paidOrder(owner: $owner);
        $unpaid['order']->payments()->update(['status' => PaymentStatus::Pending]);

        $this->asUser($owner)
            ->postJson($this->quoteUrl($pending['order']), $this->recipient())
            ->assertStatus(422)
            ->assertJsonPath('message', 'لا يمكن شحن طلب دفعه اليدوي لم يُراجع بعد.');

        $this->asUser($owner)
            ->postJson($this->quoteUrl($unpaid['order']), $this->recipient())
            ->assertStatus(422)
            ->assertJsonPath('message', 'لا يمكن إنشاء شحنة قبل تأكيد الدفع.');

        Http::assertNothingSent();
        $this->assertDatabaseCount('oto_shipments', 0);
    }

    public function test_invalid_token_and_network_failure_do_not_create_a_shipment(): void
    {
        ['owner' => $owner, 'order' => $order] = $this->paidOrder();
        Http::fake([
            '*refreshToken*' => Http::response(['message' => 'invalid refresh token'], 401),
        ]);

        $response = $this->asUser($owner)->postJson($this->quoteUrl($order), $this->recipient());
        $response->assertStatus(502);
        $this->assertStringNotContainsString('test-refresh-token', $response->getContent());
        $this->assertStringNotContainsString('invalid refresh token', $response->getContent());
        $this->assertNull(OtoShipment::query()->first()?->shipment_id);
        $this->assertNull(OtoShipment::query()->first()?->remote_order_created_at);

        Cache::flush();
        Http::fake([
            '*refreshToken*' => Http::response(['access_token' => 'test-access', 'expires_in' => 3600]),
            '*checkOTODeliveryFee*' => function () {
                throw new ConnectionException('timed out');
            },
        ]);

        $this->asUser($owner)->postJson($this->quoteUrl($order), $this->recipient())->assertStatus(502);
        $this->assertNull(OtoShipment::query()->first()?->shipment_id);
        $this->assertSame('error', OtoShipment::query()->first()?->status);
        $this->assertSame(0, $this->sentCount('createOrder'));
        $this->assertSame(0, $this->sentCount('createShipment'));
    }

    public function test_access_token_is_refreshed_once_after_unauthorized_response(): void
    {
        ['owner' => $owner, 'order' => $order] = $this->paidOrder();
        Http::fake([
            '*refreshToken*' => Http::sequence()
                ->push(['access_token' => 'old-token', 'expires_in' => 3600])
                ->push(['access_token' => 'new-token', 'expires_in' => 3600]),
            '*checkOTODeliveryFee*' => Http::sequence()
                ->push(['message' => 'unauthorized'], 401)
                ->push(['deliveryCompany' => [[
                    'deliveryOptionId' => 2828,
                    'deliveryOptionName' => 'SMSA Express',
                    'deliveryCompanyName' => 'SMSA',
                    'price' => 25,
                    'currency' => 'SAR',
                ]]], 200),
        ]);

        $this->asUser($owner)->postJson($this->quoteUrl($order), $this->recipient())->assertOk();
        $this->assertSame(2, $this->sentCount('refreshToken'));
        $this->assertSame(2, $this->sentCount('checkOTODeliveryFee'));
    }

    public function test_queue_hides_secrets_and_blocks_other_roles(): void
    {
        ['owner' => $owner, 'customer' => $customer, 'order' => $order] = $this->paidOrder();
        $manager = User::factory()->accountManager()->create();

        $response = $this->asUser($owner)->getJson('/api/admin/shipping/oto');
        $response->assertOk()
            ->assertJsonPath('data.integration.enabled', true)
            ->assertJsonPath('data.integration.sandbox', true)
            ->assertJsonPath('data.integration.order_code_used', false)
            ->assertJsonPath('data.items.0.id', $order->id)
            ->assertJsonMissingPath('data.integration.refresh_token');
        $this->assertStringNotContainsString('test-refresh-token', $response->getContent());
        $this->assertStringNotContainsString('test-webhook-secret', $response->getContent());

        $this->asUser($customer)->getJson('/api/admin/shipping/oto')->assertForbidden();
        $this->asUser($manager)->postJson($this->quoteUrl($order), $this->recipient())->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
        $this->getJson('/api/admin/shipping/oto')->assertUnauthorized();

        $other = User::factory()->create();
        $this->asUser($other)->getJson('/api/customer/orders/'.$order->id)->assertForbidden();
    }

    public function test_webhook_updates_shipment_without_touching_payment_or_order_state(): void
    {
        ['order' => $order] = $this->paidOrder();
        OtoShipment::query()->create([
            'order_id' => $order->id,
            'oto_order_id' => $order->reference,
            'status' => 'booked',
            'shipment_id' => 'SHP-1',
            'currency' => 'SAR',
            'remote_order_created_at' => now(),
        ]);

        $timestamp = (string) (int) round(microtime(true) * 1000);
        $payload = [
            'orderId' => $order->reference,
            'status' => 'inTransit',
            'trackingNumber' => 'TRK-WEB',
            'trackingURL' => 'https://track.example/TRK-WEB',
            'deliveryCompany' => 'SMSA',
            'timestamp' => $timestamp,
        ];
        $payload['signature'] = OtoSignature::orderStatus($order->reference, 'inTransit', $timestamp, 'test-webhook-secret');

        $this->postJson('/api/webhooks/oto', $payload)
            ->assertOk()
            ->assertJsonPath('data.accepted', true);

        $shipment = OtoShipment::query()->first();
        $this->assertSame('inTransit', $shipment?->status);
        $this->assertSame('TRK-WEB', $shipment?->tracking_number);
        $this->assertSame('https://track.example/TRK-WEB', $shipment?->tracking_url);
        $this->assertSame(PaymentStatus::Paid, $order->payments()->first()?->status);
        $this->assertSame(OrderStatus::Confirmed, $order->fresh()?->status);
        $this->assertNull($order->fresh()?->delivered_at);

        $this->postJson('/api/webhooks/oto', $payload)
            ->assertOk()
            ->assertJsonPath('data.duplicate', true);
        $this->assertSame(1, OtoWebhookEvent::query()->count());
    }

    public function test_webhook_rejects_bad_signatures_stale_timestamps_and_disabled_calls(): void
    {
        ['order' => $order] = $this->paidOrder();
        $timestamp = (string) (int) round(microtime(true) * 1000);
        $payload = [
            'orderId' => $order->reference,
            'status' => 'delivered',
            'timestamp' => $timestamp,
            'signature' => 'not-a-real-signature',
        ];

        $this->postJson('/api/webhooks/oto', $payload)->assertUnauthorized();
        $this->assertDatabaseCount('oto_webhook_events', 0);

        $stale = (string) ((int) round(microtime(true) * 1000) - 3_600_000);
        $stalePayload = [
            'orderId' => $order->reference,
            'status' => 'delivered',
            'timestamp' => $stale,
            'signature' => OtoSignature::orderStatus($order->reference, 'delivered', $stale, 'test-webhook-secret'),
        ];
        $this->postJson('/api/webhooks/oto', $stalePayload)->assertUnauthorized();
        $this->assertDatabaseCount('oto_webhook_events', 0);

        config(['oto.webhook_secret' => '']);
        $payload['signature'] = OtoSignature::orderStatus($order->reference, 'delivered', $timestamp, '');
        $this->postJson('/api/webhooks/oto', $payload)->assertUnauthorized();

        config(['oto.enabled' => false, 'oto.webhook_secret' => 'test-webhook-secret']);
        $payload['signature'] = OtoSignature::orderStatus($order->reference, 'delivered', $timestamp, 'test-webhook-secret');
        $this->postJson('/api/webhooks/oto', $payload)->assertNotFound();
    }

    public function test_shipment_error_and_older_status_do_not_roll_back_delivery(): void
    {
        ['order' => $order] = $this->paidOrder();
        $shipment = OtoShipment::query()->create([
            'order_id' => $order->id,
            'oto_order_id' => $order->reference,
            'status' => 'booked',
            'shipment_id' => 'SHP-1',
            'currency' => 'SAR',
            'remote_order_created_at' => now(),
        ]);

        $deliveredAt = (string) (int) round(microtime(true) * 1000);
        $delivered = [
            'orderId' => $order->reference,
            'status' => 'delivered',
            'timestamp' => $deliveredAt,
        ];
        $delivered['signature'] = OtoSignature::orderStatus($order->reference, 'delivered', $deliveredAt, 'test-webhook-secret');
        $this->postJson('/api/webhooks/oto', $delivered)->assertOk();

        $shipment->refresh();
        $this->assertSame('delivered', $shipment->status);
        $this->assertNotNull($shipment->delivered_at);
        $this->assertNull($order->fresh()?->delivered_at);

        $older = (string) (((int) $deliveredAt) - 600_000);
        $olderPayload = [
            'orderId' => $order->reference,
            'status' => 'inTransit',
            'timestamp' => $older,
            'signature' => OtoSignature::orderStatus($order->reference, 'inTransit', $older, 'test-webhook-secret'),
        ];
        $this->postJson('/api/webhooks/oto', $olderPayload)->assertOk();
        $this->assertSame('delivered', $shipment->fresh()?->status);

        $errorAt = (string) (int) round(microtime(true) * 1000);
        $error = [
            'orderId' => $order->reference,
            'errorCode' => 'OTO1001',
            'errorMessage' => 'Carrier rejected the shipment',
            'deliveryCompany' => 'SMSA',
            'timestamp' => $errorAt,
        ];
        $error['signature'] = OtoSignature::shipmentError($order->reference, 'OTO1001', $errorAt, 'test-webhook-secret');
        $this->postJson('/api/webhooks/oto', $error)->assertOk();

        $shipment->refresh();
        $this->assertSame('error', $shipment->status);
        $this->assertSame('OTO1001', $shipment->error_code);
        $this->assertSame(PaymentStatus::Paid, $order->payments()->first()?->status);
        $this->assertSame(OrderStatus::Confirmed, $order->fresh()?->status);
    }

    public function test_owner_can_sync_tracking_after_a_failure(): void
    {
        ['owner' => $owner, 'order' => $order] = $this->paidOrder();
        OtoShipment::query()->create([
            'order_id' => $order->id,
            'oto_order_id' => $order->reference,
            'status' => 'error',
            'shipment_id' => 'SHP-1',
            'currency' => 'SAR',
            'error_message' => 'تعذر الاتصال',
            'remote_order_created_at' => now(),
            'recipient_name' => 'نورة القحطاني',
            'recipient_phone' => '0551234567',
            'address' => 'شارع التخصصي، الرياض',
            'city' => 'Riyadh',
            'country' => 'SA',
        ]);
        Http::fake([
            '*refreshToken*' => Http::response(['access_token' => 'test-access', 'expires_in' => 3600]),
            '*orderStatus*' => Http::response([
                'status' => 'outForDelivery',
                'trackingNumber' => 'TRK-SYNC',
                'trackingURL' => 'https://track.example/TRK-SYNC',
            ]),
        ]);

        $this->asUser($owner)
            ->postJson('/api/admin/orders/'.$order->id.'/oto/retry')
            ->assertOk()
            ->assertJsonPath('data.status', 'outForDelivery')
            ->assertJsonPath('data.tracking_number', 'TRK-SYNC')
            ->assertJsonPath('data.error_message', null);

        $this->assertSame(PaymentStatus::Paid, $order->payments()->first()?->status);
    }

    /**
     * @return array{owner: User, customer: User, order: Order}
     */
    private function paidOrder(bool $withItem = false, ?User $owner = null): array
    {
        $owner ??= User::factory()->owner()->create();
        $customer = User::factory()->create();
        $order = Order::factory()->create([
            'customer_id' => $customer->id,
            'status' => OrderStatus::Confirmed,
            'title' => 'طلب هوية بصرية',
        ]);
        Payment::factory()->paid()->create([
            'order_id' => $order->id,
            'customer_id' => $customer->id,
            'amount' => '1500.00',
            'currency' => 'SAR',
        ]);

        if ($withItem) {
            $service = Service::factory()->create(['name' => 'تصميم شعار']);
            OrderItem::query()->create([
                'order_id' => $order->id,
                'service_id' => $service->id,
                'quantity' => 2,
                'unit_price' => '100.00',
                'currency' => 'SAR',
            ]);
        }

        return compact('owner', 'customer', 'order');
    }

    /**
     * @return array<string, mixed>
     */
    private function recipient(): array
    {
        return [
            'recipient_name' => 'نورة القحطاني',
            'recipient_phone' => '0551234567',
            'address' => 'شارع التخصصي، الرياض',
            'city' => 'Riyadh',
            'country' => 'SA',
            'weight_kg' => 1.5,
        ];
    }

    private function enableOto(): void
    {
        config([
            'oto.enabled' => true,
            'oto.base_url' => 'https://staging-api.tryoto.com/rest/v2',
            'oto.refresh_token' => 'test-refresh-token',
            'oto.pickup_location_code' => 'wh_test',
            'oto.webhook_secret' => 'test-webhook-secret',
            'oto.origin_city' => 'Riyadh',
            'oto.origin_country' => 'SA',
            'oto.order_code' => '2001',
            'oto.retry_sleep_ms' => 0,
            'oto.timeout' => 5,
            'oto.currency' => 'SAR',
        ]);
    }

    private function fakeOto(): void
    {
        Http::fake([
            '*refreshToken*' => Http::response(['access_token' => 'test-access', 'expires_in' => 3600]),
            '*checkOTODeliveryFee*' => Http::response(['deliveryCompany' => [[
                'deliveryOptionId' => 2828,
                'deliveryOptionName' => 'SMSA Express',
                'deliveryCompanyName' => 'SMSA',
                'price' => 25,
                'currency' => 'SAR',
                'avgDeliveryTime' => '2-4 days',
            ]]]),
            '*orderDetails*' => Http::sequence()
                ->push(['success' => false, 'otoErrorCode' => 'OTO1002'], 404)
                ->push(['success' => true, 'otoId' => 9001]),
            '*createOrder*' => Http::response(['success' => true, 'otoId' => 9001]),
            '*orderStatus*' => Http::response(['success' => true, 'status' => 'new']),
            '*createShipment*' => Http::response([
                'success' => true,
                'shipmentId' => 'SHP-1',
                'trackingNumber' => 'TRK1',
            ]),
            '*print/*' => Http::response([
                'printAWBURL' => 'https://labels.example/awb.pdf',
                'trackingNumber' => 'TRK1',
            ]),
        ]);
    }

    private function quoteUrl(Order $order): string
    {
        return '/api/admin/orders/'.$order->id.'/oto/quote';
    }

    private function orderUrl(Order $order): string
    {
        return '/api/admin/orders/'.$order->id.'/oto/order';
    }

    private function shipmentUrl(Order $order): string
    {
        return '/api/admin/orders/'.$order->id.'/oto/shipment';
    }

    private function sentCount(string $fragment): int
    {
        return collect(Http::recorded())
            ->filter(fn (array $pair) => str_contains($pair[0]->url(), $fragment))
            ->count();
    }

    private function tokenFor(User $user): string
    {
        return $user->createToken('auth')->plainTextToken;
    }

    private function asUser(User $user): self
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withToken($this->tokenFor($user));
    }
}
