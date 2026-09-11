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

        $issues = [];

        if (! $enabled) {
            $issues[] = 'PayTabs is disabled via PAYTABS_ENABLED.';
        }

        if ($profileId === '' || (int) $profileId <= 0) {
            $issues[] = 'PAYTABS_PROFILE_ID is missing or invalid.';
        }

        if ($serverKey === '') {
            $issues[] = 'PAYTABS_SERVER_KEY is not set.';
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

        $callbackUrl = rtrim((string) config('app.url'), '/').'/api/webhooks/paytabs';
        $returnUrl = rtrim((string) config('app.url'), '/').'/api/payments/paytabs/return';
        $callbackOk = $this->urlLooksPublic($callbackUrl);
        $returnOk = $this->urlLooksPublic($returnUrl);

        if (! $callbackOk) {
            $issues[] = 'Callback URL may be unreachable (check APP_URL).';
        }
        if (! $returnOk) {
            $issues[] = 'Return URL may be unreachable (check APP_URL).';
        }

        $hasServerKey = $serverKey !== '';
        $configured = $enabled
            && (int) $profileId > 0
            && $hasServerKey
            && $baseUrl !== ''
            && is_string($baseHost);

        return [
            'configured' => $configured,
            'enabled' => $enabled,
            'environment' => $environment,
            'base_host' => $baseHost,
            'profile_id_hint' => $this->profileIdHint($profileId),
            'has_server_key' => $hasServerKey,
            'callback_url_ok' => $callbackOk,
            'return_url_ok' => $returnOk,
            'issues' => $issues,
        ];
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
