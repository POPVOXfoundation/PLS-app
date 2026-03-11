<?php

namespace App\Domain\Reviews\Enums;

enum PlsStepStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Skipped = 'skipped';
}
