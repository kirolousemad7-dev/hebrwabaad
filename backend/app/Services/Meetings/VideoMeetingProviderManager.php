<?php

namespace App\Services\Meetings;

use App\Contracts\VideoMeetingProvider;
use App\Enums\MeetingProvider;
use Illuminate\Validation\ValidationException;

class VideoMeetingProviderManager
{
    public function __construct(
        private readonly GoogleMeetProvider $googleMeet,
        private readonly ZoomMeetingProvider $zoom,
        private readonly NullMeetingProvider $none,
    ) {}

    public function driver(MeetingProvider|string $provider): VideoMeetingProvider
    {
        $enum = $provider instanceof MeetingProvider
            ? $provider
            : MeetingProvider::from((string) $provider);

        return match ($enum) {
            MeetingProvider::GoogleMeet => $this->googleMeet,
            MeetingProvider::Zoom => $this->zoom,
            MeetingProvider::None => $this->none,
        };
    }

    /**
     * @return array<string, array{provider: string, label_ar: string, configured: bool}>
     */
    public function availability(): array
    {
        return [
            MeetingProvider::GoogleMeet->value => [
                'provider' => MeetingProvider::GoogleMeet->value,
                'label_ar' => MeetingProvider::GoogleMeet->labelAr(),
                'configured' => $this->googleMeet->isConfigured(),
            ],
            MeetingProvider::Zoom->value => [
                'provider' => MeetingProvider::Zoom->value,
                'label_ar' => MeetingProvider::Zoom->labelAr(),
                'configured' => $this->zoom->isConfigured(),
            ],
            MeetingProvider::None->value => [
                'provider' => MeetingProvider::None->value,
                'label_ar' => MeetingProvider::None->labelAr(),
                'configured' => true,
            ],
        ];
    }

    public function assertAvailable(MeetingProvider $provider): VideoMeetingProvider
    {
        $driver = $this->driver($provider);
        if (! $driver->isConfigured()) {
            throw ValidationException::withMessages([
                'provider' => [$provider->labelAr().' is not configured.'],
            ]);
        }

        return $driver;
    }
}
