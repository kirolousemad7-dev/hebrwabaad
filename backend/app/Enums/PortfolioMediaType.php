<?php

namespace App\Enums;

enum PortfolioMediaType: string
{
    case Image = 'IMAGE';
    case UploadedVideo = 'UPLOADED_VIDEO';
    case ExternalVideo = 'EXTERNAL_VIDEO';
    case WebsiteLink = 'WEBSITE_LINK';
    case ExternalProjectLink = 'EXTERNAL_PROJECT_LINK';

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
            self::Image => 'صورة',
            self::UploadedVideo => 'فيديو مرفوع',
            self::ExternalVideo => 'فيديو خارجي',
            self::WebsiteLink => 'رابط موقع',
            self::ExternalProjectLink => 'رابط مشروع',
        };
    }

    public function isLink(): bool
    {
        return in_array($this, [self::WebsiteLink, self::ExternalProjectLink], true);
    }

    public function isVideo(): bool
    {
        return in_array($this, [self::UploadedVideo, self::ExternalVideo], true);
    }
}
