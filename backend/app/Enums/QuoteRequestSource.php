<?php

namespace App\Enums;

enum QuoteRequestSource: string
{
    case Service = 'SERVICE';
    case Package = 'PACKAGE';
    case PackageTier = 'PACKAGE_TIER';
    case CustomPackage = 'CUSTOM_PACKAGE';
    case PrintingRequest = 'PRINTING_REQUEST';
    case EventRequest = 'EVENT_REQUEST';
    case Order = 'ORDER';
    case ConsultantRecommendation = 'CONSULTANT_RECOMMENDATION';
    case Portfolio = 'PORTFOLIO';

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
            self::Service => 'خدمة',
            self::Package => 'باقة',
            self::PackageTier => 'مستوى باقة',
            self::CustomPackage => 'باقة مخصصة',
            self::PrintingRequest => 'طلب طباعة',
            self::EventRequest => 'طلب فعالية',
            self::Order => 'طلب',
            self::ConsultantRecommendation => 'توصية المستشار',
            self::Portfolio => 'معرض أعمال',
        };
    }

    public function usesPrintingQuotation(): bool
    {
        return $this === self::PrintingRequest;
    }
}
