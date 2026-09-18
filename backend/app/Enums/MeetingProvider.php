<?php

namespace App\Enums;

enum MeetingProvider: string
{
    case GoogleMeet = 'GOOGLE_MEET';
    case Zoom = 'ZOOM';
    case None = 'NONE';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::GoogleMeet => 'Google Meet',
            self::Zoom => 'Zoom',
            self::None => 'بدون اجتماع',
        };
    }

    public function createsRemoteSession(): bool
    {
        return $this !== self::None;
    }
}
