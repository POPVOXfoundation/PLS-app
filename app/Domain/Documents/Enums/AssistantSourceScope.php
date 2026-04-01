<?php

namespace App\Domain\Documents\Enums;

enum AssistantSourceScope: string
{
    case Global = 'global';
    case Jurisdiction = 'jurisdiction';
    case Review = 'review';
}
