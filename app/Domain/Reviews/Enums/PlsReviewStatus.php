<?php

namespace App\Domain\Reviews\Enums;

enum PlsReviewStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Completed = 'completed';
    case Archived = 'archived';
}
