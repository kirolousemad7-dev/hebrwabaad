<?php

namespace App\Enums;

enum MarketingSectionType: string
{
    case Hero = 'hero';
    case Services = 'services';
    case Packages = 'packages';
    case About = 'about';
    case WhyUs = 'why-us';
    case Process = 'process';
    case Portfolio = 'portfolio';
    case Suppliers = 'suppliers';
    case BuildPackage = 'build-package';
    case FinalCta = 'final-cta';
    case Contact = 'contact';
    case Custom = 'custom';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::cases(),
        );
    }

    public function labelAr(): string
    {
        return match ($this) {
            self::Hero => 'البطل',
            self::Services => 'الخدمات',
            self::Packages => 'الباقات',
            self::About => 'من نحن',
            self::WhyUs => 'لماذا نحن',
            self::Process => 'مسار العمل',
            self::Portfolio => 'أعمالنا',
            self::Suppliers => 'الموردين',
            self::BuildPackage => 'صمّم باقتك',
            self::FinalCta => 'دعوة نهائية',
            self::Contact => 'تواصل',
            self::Custom => 'مخصص',
        };
    }
}
