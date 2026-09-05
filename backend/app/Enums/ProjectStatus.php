<?php

namespace App\Enums;

enum ProjectStatus: string
{
    case Planning = 'PLANNING';
    case InProgress = 'IN_PROGRESS';
    case Review = 'REVIEW';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
}
