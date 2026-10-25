<?php

namespace App\Services\Shipping;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\OtoShipment;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OtoShipmentService
{
    public function __construct(private readonly OtoClient $client) {}

    /**
     * @return array{enabled: bool, configured: bool, sandbox: bool, order_code_used: bool}
     */
    public function integrationStatus(): array
    {
        $base = (string) config('oto.base_url');

        return [
            'enabled' => $this->client->enabled(),
            'configured' => $this->client->enabled()
                && (string) config('oto.refresh_token') !== ''
                && (string) config('oto.pickup_location_code') !== '',
            'sandbox' => str_contains($base, 'staging-api.tryoto.com'),
            'order_code_used' => false,
        ];
    }

    public function queue(string $view): LengthAwarePaginator
    {
        $query = Order::query()
            ->whereHas('payments', fn ($payments) => $payments->where('status', PaymentStatus::Paid->value))
            ->whereDoesntHave('payments', fn ($payments) => $payments->whereIn('status', [
                PaymentStatus::Pending->value,
                PaymentStatus::Processing->value,
                PaymentStatus::PendingVerification->value,
            ]))
            ->with(['customer', 'otoShipment'])
            ->latest('id');

        if ($view === 'errors') {
            $query->whereHas('otoShipment', function ($shipments): void {
                $shipments->where('status', 'error')->orWhereNotNull('error_message');
            });
        } elseif ($view === 'shipped') {
            $query->whereHas('otoShipment', fn ($shipments) => $shipments->whereNotNull('shipment_id'));
        } elseif ($view !== 'all') {
            $query->where(function ($orders): void {
                $orders->whereDoesntHave('otoShipment')
                    ->orWhereHas('otoShipment', function ($shipments): void {
                        $shipments->whereNull('shipment_id')->orWhere('status', 'error');
                    });
            });
        }

        return $query->paginate(20);
    }

    /**
     * @return array<string, mixed>
     */
    public function detail(Order $order): array
    {
        $order->load(['customer', 'otoShipment', 'payments']);

        return [
            'order' => [
                'id' => $order->id,
                'reference' => $order->reference,
                'title' => $order->title,
                'customer_name' => $order->customer?->name,
            ],
            'block_reason' => $this->blockReason($order),
            'shipment' => $order->otoShipment?->ownerPayload(),
            'integration' => $this->integrationStatus(),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function quote(Order $order, array $input): OtoShipment
    {
        $this->guardShipping($order);
        $recipient = $this->recipient($input);
        $draft = $this->persistDraft($order, $recipient);

        try {
            $payload = $this->client->checkDeliveryFee([
                'originCity' => (string) config('oto.origin_city', 'Riyadh'),
                'destinationCity' => $recipient['city'],
                'originCountry' => (string) config('oto.origin_country', 'SA'),
                'destinationCountry' => $recipient['country'],
                'weight' => (float) $recipient['weight_kg'],
                'packageCount' => 1,
                'currency' => (string) config('oto.currency', 'SAR'),
                'height' => 10,
                'width' => 10,
                'length' => 10,
            ]);
        } catch (OtoApiException $exception) {
            $this->rememberError($draft, $exception);

            throw $exception;
        }

        $options = $this->options($payload);
        if ($options === []) {
            $draft->status = 'error';
            $draft->error_code = null;
            $draft->error_message = 'لا توجد شركات شحن لهذه الوجهة.';
            $draft->save();

            throw ValidationException::withMessages([
                'city' => 'لا توجد شركات شحن لهذه الوجهة.',
            ]);
        }

        $draft->quote_options = $options;
        $draft->status = $draft->remote_order_created_at ? $draft->status : 'quoted';
        $draft->error_code = null;
        $draft->error_message = null;
        $draft->save();

        return $draft->fresh() ?? $draft;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function pushOrder(Order $order, array $input): OtoShipment
    {
        $this->guardShipping($order);
        $recipient = $this->recipient($input);
        $this->persistDraft($order, $recipient);
        $this->requirePickupLocation();

        try {
            $shipment = DB::transaction(function () use ($order, $recipient): OtoShipment {
                $shipment = $this->lock($order);
                $this->fillRecipient($shipment, $recipient);

                if ($shipment->remote_order_created_at !== null) {
                    $shipment->save();

                    return $shipment;
                }

                $orderId = (string) $shipment->oto_order_id;
                $existing = $this->client->orderDetails($orderId);
                if ($this->remoteOrderExists($existing, $orderId)) {
                    return $this->markOrderCreated($shipment, $existing ?? []);
                }

                $created = $this->client->createOrder($this->orderPayload($order, $shipment));
                if (($created['success'] ?? true) === false) {
                    throw new OtoApiException(
                        $this->publicOtoMessage($created),
                        422,
                        is_string($created['otoErrorCode'] ?? null) ? $created['otoErrorCode'] : null,
                    );
                }

                return $this->markOrderCreated($shipment, $created);
            });
        } catch (OtoApiException $exception) {
            $this->rememberError(OtoShipment::query()->where('order_id', $order->id)->first(), $exception);

            throw $exception;
        }

        return $shipment->fresh() ?? $shipment;
    }

    public function book(Order $order, string $deliveryOptionId): OtoShipment
    {
        $this->guardShipping($order);
        $existing = $order->otoShipment()->first();
        if ($existing === null || $existing->remote_order_created_at === null) {
            throw ValidationException::withMessages([
                'shipment' => 'أنشئ طلب الشحن قبل حجز شركة الشحن.',
            ]);
        }

        $allowed = collect($existing->quote_options ?? [])
            ->pluck('delivery_option_id')
            ->map(fn ($id) => (string) $id)
            ->all();
        if (! in_array($deliveryOptionId, $allowed, true)) {
            throw ValidationException::withMessages([
                'delivery_option_id' => 'اختر خيار شحن من القائمة.',
            ]);
        }

        $existing->delivery_option_id = $deliveryOptionId;
        $option = collect($existing->quote_options ?? [])->firstWhere('delivery_option_id', $deliveryOptionId);
        if (is_array($option)) {
            $existing->carrier = $option['carrier'] ?: $option['name'];
            $existing->shipping_cost = $option['price'];
            $existing->currency = $option['currency'] ?: $existing->currency;
        }
        $existing->save();

        try {
            $shipment = DB::transaction(function () use ($order, $deliveryOptionId): OtoShipment {
                $shipment = $this->lock($order);
                if ($shipment->shipment_id !== null) {
                    return $shipment;
                }

                $orderId = (string) $shipment->oto_order_id;
                $remote = $this->client->orderStatus($orderId);
                if ($this->alreadyShipped($remote)) {
                    return $this->applyRemoteStatus($shipment, $remote, now());
                }

                $created = $this->client->createShipment($orderId, $deliveryOptionId);
                if (($created['success'] ?? true) === false) {
                    throw new OtoApiException(
                        $this->publicOtoMessage($created),
                        422,
                        is_string($created['otoErrorCode'] ?? null) ? $created['otoErrorCode'] : null,
                    );
                }

                $this->applyShipmentPayload($shipment, $created);
                $shipment->status = 'booked';
                $shipment->error_code = null;
                $shipment->error_message = null;
                $shipment->last_synced_at = now();
                $shipment->save();

                return $shipment;
            });
        } catch (OtoApiException $exception) {
            $this->rememberError($order->otoShipment()->first(), $exception);

            throw $exception;
        }

        return $this->attachLabel($shipment);
    }

    public function sync(Order $order): OtoShipment
    {
        $this->assertEnabled();
        $shipment = $order->otoShipment()->first();
        if ($shipment === null || $shipment->remote_order_created_at === null || $shipment->oto_order_id === null) {
            throw ValidationException::withMessages([
                'shipment' => 'لا يوجد طلب شحن لمزامنته.',
            ]);
        }

        try {
            $remote = $this->client->orderStatus($shipment->oto_order_id);
        } catch (OtoApiException $exception) {
            $this->rememberError($shipment, $exception);

            throw $exception;
        }

        return $this->applyRemoteStatus($shipment, $remote, now());
    }

    public function label(Order $order): OtoShipment
    {
        $this->assertEnabled();
        $shipment = $order->otoShipment()->first();
        if ($shipment === null || $shipment->shipment_id === null) {
            throw ValidationException::withMessages([
                'shipment' => 'لا توجد شحنة لطباعة بوليصتها.',
            ]);
        }

        if ($shipment->awb_url) {
            return $shipment;
        }

        return $this->attachLabel($shipment);
    }

    public function retry(Order $order): OtoShipment
    {
        $shipment = $order->otoShipment()->first();
        if ($shipment === null) {
            throw ValidationException::withMessages([
                'shipment' => 'أدخل عنوان التسليم أولاً.',
            ]);
        }

        $input = [
            'recipient_name' => $shipment->recipient_name,
            'recipient_phone' => $shipment->recipient_phone,
            'address' => $shipment->address,
            'city' => $shipment->city,
            'country' => $shipment->country,
            'weight_kg' => $shipment->weight_kg,
        ];

        if ($shipment->remote_order_created_at === null) {
            return $this->pushOrder($order, $input);
        }

        if ($shipment->shipment_id === null && $shipment->delivery_option_id) {
            return $this->book($order, (string) $shipment->delivery_option_id);
        }

        return $this->sync($order);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function applyWebhook(string $type, array $payload): void
    {
        $orderId = (string) ($payload['orderId'] ?? '');
        $shipment = OtoShipment::query()->where('oto_order_id', $orderId)->first();
        if ($shipment === null) {
            return;
        }

        $eventAt = $this->eventTime((string) ($payload['timestamp'] ?? ''));
        if ($type === 'shipmentError') {
            if ($this->isOlderEvent($shipment, $eventAt)) {
                return;
            }

            $shipment->status = 'error';
            $shipment->error_code = $this->nullableString($payload['errorCode'] ?? null);
            $shipment->error_message = $this->nullableString($payload['errorMessage'] ?? null) ?? 'تعذر إكمال الشحنة.';
            $shipment->carrier = $this->nullableString($payload['deliveryCompany'] ?? null) ?? $shipment->carrier;
            $shipment->last_event_at = $eventAt ?? now();
            $shipment->last_synced_at = now();
            $shipment->save();

            return;
        }

        $this->applyRemoteStatus($shipment, $payload, $eventAt);
    }

    public function blockReason(Order $order): ?string
    {
        $order->loadMissing('payments');
        $payments = $order->payments;

        if ($payments->contains(fn ($payment) => $payment->status === PaymentStatus::PendingVerification)) {
            return 'لا يمكن شحن طلب دفعه اليدوي لم يُراجع بعد.';
        }

        if ($payments->contains(fn ($payment) => $payment->status?->isOpen())) {
            return 'لا يمكن إنشاء شحنة قبل تأكيد الدفع.';
        }

        if (! $payments->contains(fn ($payment) => $payment->status === PaymentStatus::Paid)) {
            return 'لا يمكن إنشاء شحنة قبل تأكيد الدفع.';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{recipient_name: string, recipient_phone: string, address: string, city: string, country: string, weight_kg: string}
     */
    private function recipient(array $input): array
    {
        $name = trim((string) ($input['recipient_name'] ?? ''));
        $address = trim((string) ($input['address'] ?? ''));
        $city = trim((string) ($input['city'] ?? ''));
        $country = strtoupper(trim((string) ($input['country'] ?? 'SA')));

        if ($name === '' || $address === '' || $city === '') {
            throw ValidationException::withMessages([
                'address' => 'اسم المستلم والعنوان والمدينة مطلوبة.',
            ]);
        }

        if (preg_match('/^[A-Z]{2}$/', $country) !== 1) {
            throw ValidationException::withMessages([
                'country' => 'رمز الدولة يجب أن يكون حرفين.',
            ]);
        }

        return [
            'recipient_name' => $name,
            'recipient_phone' => $this->normalizePhone((string) ($input['recipient_phone'] ?? '')),
            'address' => $address,
            'city' => $city,
            'country' => $country,
            'weight_kg' => $this->weight($input['weight_kg'] ?? null),
        ];
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        $length = strlen($digits);
        if ($length < 8 || $length > 15) {
            throw ValidationException::withMessages([
                'recipient_phone' => 'رقم الجوال غير صالح.',
            ]);
        }

        return $digits;
    }

    private function weight(mixed $value): string
    {
        $weight = $value === null || $value === ''
            ? (float) config('oto.default_weight_kg', 1)
            : (float) $value;

        if ($weight < 0.1 || $weight > 100) {
            throw ValidationException::withMessages([
                'weight_kg' => 'وزن الشحنة غير صالح.',
            ]);
        }

        return number_format($weight, 2, '.', '');
    }

    private function guardShipping(Order $order): void
    {
        $this->assertEnabled();
        $reason = $this->blockReason($order);
        if ($reason !== null) {
            throw ValidationException::withMessages(['shipment' => $reason]);
        }
    }

    private function assertEnabled(): void
    {
        if (! $this->client->enabled()) {
            throw new OtoApiException('تكامل الشحن غير مفعّل.', 503);
        }
    }

    private function requirePickupLocation(): void
    {
        if ((string) config('oto.pickup_location_code') === '') {
            throw new OtoApiException('رمز موقع الاستلام غير مضبوط.', 503);
        }
    }

    /**
     * @param  array{recipient_name: string, recipient_phone: string, address: string, city: string, country: string, weight_kg: string}  $recipient
     */
    private function persistDraft(Order $order, array $recipient): OtoShipment
    {
        $shipment = OtoShipment::query()->firstOrNew(['order_id' => $order->id]);
        if ($shipment->exists && $shipment->shipment_id !== null) {
            throw ValidationException::withMessages([
                'shipment' => 'توجد شحنة لهذا الطلب بالفعل.',
            ]);
        }

        $this->fillRecipient($shipment, $recipient);
        $shipment->oto_order_id = $shipment->oto_order_id ?: $this->merchantOrderId($order);
        $shipment->currency = $shipment->currency ?: (string) config('oto.currency', 'SAR');
        $shipment->status = $shipment->status ?: 'quoted';
        $shipment->save();

        return $shipment;
    }

    /**
     * @param  array{recipient_name: string, recipient_phone: string, address: string, city: string, country: string, weight_kg: string}  $recipient
     */
    private function fillRecipient(OtoShipment $shipment, array $recipient): void
    {
        $shipment->fill($recipient);
    }

    private function lock(Order $order): OtoShipment
    {
        $shipment = OtoShipment::query()->where('order_id', $order->id)->lockForUpdate()->first();
        if ($shipment === null) {
            throw ValidationException::withMessages([
                'shipment' => 'أدخل عنوان التسليم أولاً.',
            ]);
        }

        return $shipment;
    }

    private function merchantOrderId(Order $order): string
    {
        $reference = trim((string) $order->reference);

        return $reference !== '' ? $reference : 'HEBR-'.$order->id;
    }

    /**
     * @return array<string, mixed>
     */
    private function orderPayload(Order $order, OtoShipment $shipment): array
    {
        $order->loadMissing(['customer', 'items.service', 'payments']);
        $amount = $this->amount($order);

        return [
            'orderId' => (string) $shipment->oto_order_id,
            'pickupLocationCode' => (string) config('oto.pickup_location_code'),
            'createShipment' => false,
            'payment_method' => 'paid',
            'amount' => $amount,
            'amount_due' => 0,
            'currency' => (string) ($shipment->currency ?: config('oto.currency', 'SAR')),
            'packageCount' => 1,
            'packageWeight' => (float) $shipment->weight_kg,
            'orderDate' => ($order->created_at ?? now())->timezone((string) config('app.timezone'))->format('d/m/Y H:i'),
            'customer' => [
                'name' => (string) $shipment->recipient_name,
                'email' => (string) ($order->customer?->email ?? ''),
                'mobile' => (string) $shipment->recipient_phone,
                'address' => (string) $shipment->address,
                'city' => (string) $shipment->city,
                'country' => (string) $shipment->country,
            ],
            'items' => $this->items($order, $amount),
        ];
    }

    private function amount(Order $order): float
    {
        $paid = $order->payments
            ->filter(fn ($payment) => $payment->status === PaymentStatus::Paid)
            ->sum(fn ($payment) => (float) $payment->amount);

        if ($paid > 0) {
            return round((float) $paid, 2);
        }

        return round((float) $order->items->sum(
            fn (OrderItem $item) => (float) $item->unit_price * max(1, (int) $item->quantity)
        ), 2);
    }

    /**
     * @return list<array{productId: string, name: string, price: float, quantity: int, sku: string}>
     */
    private function items(Order $order, float $amount): array
    {
        if ($order->items->isEmpty()) {
            return [[
                'productId' => (string) $order->id,
                'name' => (string) $order->title,
                'price' => $amount,
                'quantity' => 1,
                'sku' => 'ORDER-'.$order->id,
            ]];
        }

        return $order->items->map(fn (OrderItem $item) => [
            'productId' => (string) $item->service_id,
            'name' => $item->service?->name ?: (string) $order->title,
            'price' => round((float) ($item->unit_price ?? 0), 2),
            'quantity' => max(1, (int) $item->quantity),
            'sku' => 'ITEM-'.$item->id,
        ])->values()->all();
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function remoteOrderExists(?array $payload, string $orderId): bool
    {
        if ($payload === null || ($payload['success'] ?? true) === false) {
            return false;
        }

        if (isset($payload['otoId']) && $payload['otoId'] !== '' && $payload['otoId'] !== null) {
            return true;
        }

        return isset($payload['orderId']) && (string) $payload['orderId'] === $orderId;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function markOrderCreated(OtoShipment $shipment, array $payload): OtoShipment
    {
        $internal = $payload['otoId'] ?? null;
        if ($internal !== null && $internal !== '') {
            $shipment->oto_internal_id = (string) $internal;
        }

        $shipment->remote_order_created_at = $shipment->remote_order_created_at ?? now();
        $shipment->status = $shipment->shipment_id ? $shipment->status : 'order_created';
        $shipment->error_code = null;
        $shipment->error_message = null;
        $shipment->last_synced_at = now();
        $shipment->save();

        return $shipment;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function alreadyShipped(array $payload): bool
    {
        return $this->nullableString($payload['shipmentId'] ?? $payload['shipment_id'] ?? null) !== null
            || $this->nullableString($payload['trackingNumber'] ?? null) !== null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyShipmentPayload(OtoShipment $shipment, array $payload): void
    {
        $shipmentId = $this->nullableString($payload['shipmentId'] ?? $payload['shipment_id'] ?? data_get($payload, 'data.shipmentId'));
        if ($shipmentId !== null) {
            $shipment->shipment_id = $shipmentId;
        }

        $tracking = $this->nullableString($payload['trackingNumber'] ?? $payload['dcTrackingNumber'] ?? null);
        if ($tracking !== null) {
            $shipment->tracking_number = $tracking;
        }

        $trackingUrl = $this->httpsUrl($payload['trackingURL'] ?? $payload['trackingUrl'] ?? null);
        if ($trackingUrl !== null) {
            $shipment->tracking_url = $trackingUrl;
        }

        $carrier = $this->nullableString($payload['deliveryCompany'] ?? $payload['deliveryCompanyName'] ?? null);
        if ($carrier !== null) {
            $shipment->carrier = $carrier;
        }

        $awb = $this->httpsUrl($payload['printAWBURL'] ?? null);
        if ($awb !== null) {
            $shipment->awb_url = $awb;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applyRemoteStatus(OtoShipment $shipment, array $payload, ?Carbon $eventAt): OtoShipment
    {
        $older = $this->isOlderEvent($shipment, $eventAt);
        if (! $older) {
            $status = $this->nullableString($payload['status'] ?? null);
            if ($status !== null) {
                $shipment->status = $status;
                if (strtolower($status) === 'delivered' && $shipment->delivered_at === null) {
                    $shipment->delivered_at = $eventAt ?? now();
                }
            }
            $shipment->last_event_at = $eventAt ?? now();
        }

        $this->applyShipmentPayload($shipment, $payload);
        $shipment->last_synced_at = now();
        if (! $older && $shipment->status !== 'error') {
            $shipment->error_code = null;
            $shipment->error_message = null;
        }
        $shipment->save();

        return $shipment->fresh() ?? $shipment;
    }

    private function attachLabel(OtoShipment $shipment): OtoShipment
    {
        if ($shipment->awb_url || $shipment->oto_order_id === null || $shipment->shipment_id === null) {
            return $shipment->fresh() ?? $shipment;
        }

        try {
            $printed = $this->client->printAwb($shipment->oto_order_id);
        } catch (OtoApiException $exception) {
            $shipment->error_message = $shipment->error_message ?: 'تعذر جلب بوليصة الشحن. يمكن إعادة المحاولة.';
            $shipment->error_code = $shipment->error_code ?: $exception->otoCode;
            $shipment->save();

            return $shipment->fresh() ?? $shipment;
        }

        $shipment->awb_url = $this->httpsUrl($printed['printAWBURL'] ?? null) ?? $shipment->awb_url;
        $tracking = $this->nullableString($printed['trackingNumber'] ?? null);
        if ($tracking !== null && $shipment->tracking_number === null) {
            $shipment->tracking_number = $tracking;
        }
        $shipment->save();

        return $shipment->fresh() ?? $shipment;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array{delivery_option_id: string, name: string, carrier: string, price: ?string, currency: string, delivery_time: string}>
     */
    private function options(array $payload): array
    {
        $rows = $payload['deliveryCompany'] ?? $payload['deliveryOptions'] ?? $payload['data'] ?? [];
        if (! is_array($rows)) {
            return [];
        }

        $options = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $id = $row['deliveryOptionId'] ?? $row['id'] ?? null;
            if ($id === null || $id === '') {
                continue;
            }

            $options[] = [
                'delivery_option_id' => (string) $id,
                'name' => (string) ($row['deliveryOptionName'] ?? $row['name'] ?? 'خيار شحن'),
                'carrier' => (string) ($row['deliveryCompanyName'] ?? $row['carrier'] ?? ''),
                'price' => isset($row['price']) ? (string) $row['price'] : null,
                'currency' => (string) ($row['currency'] ?? config('oto.currency', 'SAR')),
                'delivery_time' => (string) ($row['avgDeliveryTime'] ?? $row['deliveryTime'] ?? ''),
            ];
        }

        return $options;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function publicOtoMessage(array $payload): string
    {
        $message = $payload['otoErrorMessage'] ?? $payload['message'] ?? null;
        if (! is_string($message) || preg_match('/token|secret|authorization/i', $message) === 1) {
            return 'رفضت خدمة الشحن الطلب.';
        }

        return mb_substr($message, 0, 300);
    }

    private function rememberError(?OtoShipment $shipment, OtoApiException $exception): void
    {
        if ($shipment === null) {
            return;
        }

        $shipment->status = $shipment->shipment_id ? $shipment->status : 'error';
        $shipment->error_code = $exception->otoCode;
        $shipment->error_message = $exception->getMessage();
        $shipment->save();
    }

    private function isOlderEvent(OtoShipment $shipment, ?Carbon $eventAt): bool
    {
        return $eventAt !== null
            && $shipment->last_event_at !== null
            && $eventAt->lessThan($shipment->last_event_at);
    }

    private function eventTime(string $timestamp): ?Carbon
    {
        if ($timestamp === '' || preg_match('/^\d+$/', $timestamp) !== 1) {
            return null;
        }

        $value = (int) $timestamp;
        if ($value > 1_000_000_000_000) {
            return Carbon::createFromTimestampMs($value);
        }

        return Carbon::createFromTimestamp($value);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        return $text === '' ? null : mb_substr($text, 0, 2000);
    }

    private function httpsUrl(mixed $value): ?string
    {
        $url = $this->nullableString($value);
        if ($url === null || ! str_starts_with($url, 'https://')) {
            return null;
        }

        return $url;
    }
}
