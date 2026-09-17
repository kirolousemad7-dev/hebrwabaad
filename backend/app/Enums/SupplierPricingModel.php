<?php

namespace App\Enums;

enum SupplierPricingModel: string
{
    case Fixed = 'FIXED';
    case Hourly = 'HOURLY';
    case Daily = 'DAILY';
    case PerProject = 'PER_PROJECT';
    case PerUnit = 'PER_UNIT';
    case CustomQuote = 'CUSTOM_QUOTE';
}
