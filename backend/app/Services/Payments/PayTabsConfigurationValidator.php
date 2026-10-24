<?php

namespace App\Services\Payments;

class PayTabsConfigurationValidator
{
    /**
     * Safe PayTabs configuration status for ops surfaces.
     * Never includes the server key or other secrets.
     *
     * @return array{
     *     configured: bool,
     *     enabled: bool,
     *     environment: string,
     *     base_host: string|null,
     *     profile_id_hint: string|null,
     *     has_server_key: bool,
     *     has_profile_id: bool,
     *     has_merchant_id: bool,
     *     has_public_key: bool,
     *     has_secret_key: bool,
     *     integration: string,
     *     region: string,
     *     currency: string,
     *     hosted_page_message: string|null,
     *     callback_url_ok: bool,
     *     return_url_ok: bool,
     *     issues: list<string>
     * }
     */
    public function status(): array
    {
        $enabled = (bool) config('payments.enabled', true);
        $profileId = (string) config('payments.paytabs.profile_id', '');
        $serverKey = (string) config('payments.paytabs.server_key', '');
        $baseUrl = rtrim((string) config('payments.paytabs.base_url', ''), '/');
        $environment = strtolower((string) config('payments.paytabs.environment', 'test'));
        $hasProfileId = $profileId !== '' && (int) $profileId > 0;
        $hasServerKey = $serverKey !== '';
        $hasMerchantId = $this->isPresent(config('payments.paytabs.merchant_id'));
        $hasPublicKey = $this->isPresent(config('payments.paytabs.public_key'));
        $hasSecretKey = $this->isPresent(config('payments.paytabs.secret_key'));

        $issues = [];

        if (! $enabled) {
            $issues[] = 'PayTabs is disabled via PAYTABS_ENABLED.';
        }

        if (! $hasProfileId || ! $hasServerKey) {
            $issues[] = PayTabsClient::HOSTED_PAGE_REQUIRED;
        }

        if (! $hasProfileId) {
            $issues[] = 'PAYTABS_PROFILE_ID is missing or invalid.';
        }

        if (! $hasServerKey) {
            $issues[] = 'PAYTABS_SERVER_KEY is not set.';
        }

        if (($hasMerchantId || $hasPublicKey || $hasSecretKey) && (! $hasProfileId || ! $hasServerKey)) {
            $issues[] = 'PAYTABS_MERCHANT_ID, PAYTABS_PUBLIC_KEY, and PAYTABS_SECRET_KEY are not used for Hosted Payment Page.';
        }

        $baseHost = null;
        if ($baseUrl === '') {
            $issues[] = 'PAYTABS_BASE_URL is not set.';
        } else {
            $host = parse_url($baseUrl, PHP_URL_HOST);
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME);
            if (! is_string($host) || $host === '') {
                $issues[] = 'PAYTABS_BASE_URL host is invalid.';
            } else {
                $baseHost = $host;
            }
            if ($scheme !== 'https') {
                $issues[] = 'PAYTABS_BASE_URL should use https.';
            }
        }

        if (! in_array($environment, ['test', 'live', 'sandbox'], true)) {
            $issues[] = 'PAYTABS_ENVIRONMENT should be test or live.';
        }

        $callbackUrl = app(PayTabsClient::class)->callbackUrl();
        $returnUrl = app(PayTabsClient::class)->returnUrl();
        $callbackOk = $this->urlLooksPublic($callbackUrl);
        $returnOk = $this->urlLooksPublic($returnUrl);

        if (! $callbackOk) {
            $issues[] = 'Callback URL may be unreachable (check APP_URL).';
        }
        if (! $returnOk) {
            $issues[] = 'Return URL may be unreachable (check APP_URL).';
        }

        $configured = $enabled
            && $hasProfileId
            && $hasServerKey
            && $baseUrl !== ''
            && is_string($baseHost);

        return [
            'configured' => $configured,
            'enabled' => $enabled,
            'integration' => 'hosted_payment_page',
            'environment' => $environment,
            'region' => strtoupper((string) config('payments.paytabs.region', 'SAU')),
            'currency' => strtoupper((string) config('payments.paytabs.currency', 'SAR')),
            'base_host' => $baseHost,
            'profile_id_hint' => $this->profileIdHint($profileId),
            'has_profile_id' => $hasProfileId,
            'has_server_key' => $hasServerKey,
            'has_merchant_id' => $hasMerchantId,
            'has_public_key' => $hasPublicKey,
            'has_secret_key' => $hasSecretKey,
            'hosted_page_message' => ($hasProfileId && $hasServerKey) ? null : PayTabsClient::HOSTED_PAGE_REQUIRED,
            'callback_url_ok' => $callbackOk,
            'return_url_ok' => $returnOk,
            'issues' => $issues,
        ];
    }

    private function isPresent(mixed $value): bool
    {
        return is_string($value) && trim($value) !== '';
    }

    private function profileIdHint(string $profileId): ?string
    {
        $digits = preg_replace('/\D+/', '', $profileId) ?? '';
        if ($digits === '') {
            return null;
        }

        return strlen($digits) <= 4 ? $digits : substr($digits, -4);
    }

    private function urlLooksPublic(string $url): bool
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return false;
        }

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;

        if (! is_string($scheme) || ! in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        if (! is_string($host) || $host === '') {
            return false;
        }

        return true;
    }
}
