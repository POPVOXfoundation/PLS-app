<?php

namespace App\Domain\Institutions\Enums;

enum ReviewGroupType: string
{
    case Committee = 'committee';
    case Office = 'office';
    case Other = 'other';
}
