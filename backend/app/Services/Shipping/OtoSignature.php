<?php

namespace App\Services\Shipping;

/**
 * OTO signs webhook bodies with HMAC-SHA256 over a colon-joined string,
 * raw binary output, then Base64. orderStatus uses orderId:status:timestamp.
 * shipmentError uses orderId:errorCode:timestamp.
 */
final class OtoSignature
{
    public static function orderStatus(string $orderId, string $status, string $timestamp, string $secret): string
    {
        return self::sign($orderId.':'.$status.':'.$timestamp, $secret);
    }

    public static function shipmentError(string $orderId, string $errorCode, string $timestamp, string $secret): string
    {
        return self::sign($orderId.':'.$errorCode.':'.$timestamp, $secret);
    }

    public static function sign(string $payload, string $secret): string
    {
        return base64_encode(hash_hmac('sha256', $payload, $secret, true));
    }

    public static function matches(?string $provided, string $expected): bool
    {
        if (! is_string($provided) || $provided === '') {
            return false;
        }

        return hash_equals($expected, $provided);
    }
}
