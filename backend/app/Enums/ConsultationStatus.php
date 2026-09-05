<?php

namespace App\Enums;

enum ConsultationStatus: string
{
    case InProgress = 'IN_PROGRESS';
    case Completed = 'COMPLETED';
    case Abandoned = 'ABANDONED';
}
