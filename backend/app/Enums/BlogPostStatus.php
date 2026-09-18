<?php

namespace App\Enums;

enum BlogPostStatus: string
{
    case Draft = 'DRAFT';
    case Scheduled = 'SCHEDULED';
    case Published = 'PUBLISHED';
    case Archived = 'ARCHIVED';

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
            self::Draft => 'مسودة',
            self::Scheduled => 'مجدول',
            self::Published => 'منشور',
            self::Archived => 'مؤرشف',
        };
    }
}
