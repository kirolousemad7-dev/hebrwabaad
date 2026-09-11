<?php

namespace App\Enums;

enum SupplierContentType: string
{
    case Profile = 'profile';
    case ProfileVersion = 'profile_version';
    case Portfolio = 'portfolio';
    case Product = 'product';
}
