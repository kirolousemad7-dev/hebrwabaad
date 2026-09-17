<?php

namespace App\Enums;

enum SupplierVisibility: string
{
    case Internal = 'INTERNAL';
    case Public = 'PUBLIC';
    case Private = 'PRIVATE';
}
