<?php

namespace App\Enums;

enum TaskStatus: string
{
    case Todo = 'TODO';
    case InProgress = 'IN_PROGRESS';
    case Review = 'REVIEW';
    case Revision = 'REVISION';
    case Completed = 'COMPLETED';
}
