<?php

namespace App\Support;

final class SeoPages
{
    /**
     * Public marketing pages that Owner/Admin may configure.
     *
     * @var list<string>
     */
    public const KEYS = [
        'home',
        'services',
        'packages',
        'portfolio',
        'about',
        'contact',
        'suppliers',
        'marketing-packages',
        'event-packages',
        'printing-packaging',
        'consultant',
        'build-package',
    ];

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'home' => 'الرئيسية',
            'services' => 'الخدمات',
            'packages' => 'الباقات',
            'portfolio' => 'الأعمال',
            'about' => 'من نحن',
            'contact' => 'تواصل معنا',
            'suppliers' => 'الموردون',
            'marketing-packages' => 'الباقات التسويقية',
            'event-packages' => 'باقات الفعاليات',
            'printing-packaging' => 'الطباعة والتغليف',
            'consultant' => 'المستشار الذكي',
            'build-package' => 'صمّم باقتك',
        ];
    }

    public static function isValid(string $key): bool
    {
        return in_array($key, self::KEYS, true);
    }

    /**
     * @return list<string>
     */
    public static function robotsOptions(): array
    {
        return [
            'index,follow',
            'noindex,follow',
            'index,nofollow',
            'noindex,nofollow',
        ];
    }
}
