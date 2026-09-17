<?php

namespace App\Enums;

enum SupplierStatus: string
{
    case Pending = 'PENDING';
    case Active = 'ACTIVE';
    case Suspended = 'SUSPENDED';
    case Rejected = 'REJECTED';
    case Blocked = 'BLOCKED';
}
