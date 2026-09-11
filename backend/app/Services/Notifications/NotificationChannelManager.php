<?php

namespace App\Services\Notifications;

/**
 * Registry of outbound notification delivery channels.
 *
 * Only the Laravel `database` channel is registered today. Call sites should
 * resolve channels through this manager (or CalendarNotification / OperationalNotifier)
 * instead of hard-coding `via()` lists, so mail, SMS, or push can be added later
 * by registering a channel here without changing every notifier.
 *
 * Extensibility notes:
 * - Call {@see register()} with a Laravel channel name (e.g. `mail`, `vonage`,
 *   a custom push channel) when that transport is configured and tested.
 * - Prefer feature flags / config for enabling new channels in production.
 * - Do NOT add email / SMS / push preference toggles in the UI until the matching
 *   channel is registered and production-ready — unused UI flags create false expectations.
 * - PlatformNotifier and calendar flows may optionally consult {@see enabled()} so
 *   new channels apply consistently once registered.
 */
class NotificationChannelManager
{
    /**
     * @var array<string, true>
     */
    private array $channels = [
        'database' => true,
    ];

    public function __construct()
    {
        if ($this->mailTransportConfigured()) {
            $this->register('mail');
        }
    }

    /**
     * Register an additional Laravel notification channel name.
     */
    public function register(string $channel): void
    {
        $channel = trim($channel);
        if ($channel === '') {
            return;
        }

        $this->channels[$channel] = true;
    }

    public function has(string $channel): bool
    {
        return isset($this->channels[$channel]);
    }

    /**
     * Channel names currently enabled for delivery.
     *
     * @return list<string>
     */
    public function enabled(): array
    {
        return array_keys($this->channels);
    }

    /**
     * Whether a notifiable should receive via the database channel (always on today).
     */
    public function usesDatabase(): bool
    {
        return $this->has('database');
    }

    public function usesMail(): bool
    {
        return $this->has('mail');
    }

    private function mailTransportConfigured(): bool
    {
        $mailer = strtolower(trim((string) config('mail.default')));
        if ($mailer === '' || in_array($mailer, ['log', 'array', 'null'], true)) {
            return false;
        }

        return trim((string) config('mail.from.address')) !== '';
    }
}
