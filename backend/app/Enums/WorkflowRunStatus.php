<?php

namespace App\Enums;

enum WorkflowRunStatus: string
{
    case Success = 'success';
    case Skipped = 'skipped';
    case Failed = 'failed';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
