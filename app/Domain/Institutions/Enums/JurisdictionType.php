<?php

namespace App\Domain\Institutions\Enums;

enum JurisdictionType: string
{
    case National = 'national';
    case Federal = 'federal';
    case State = 'state';
    case Province = 'province';
    case Territory = 'territory';
    case Region = 'region';
    case Municipal = 'municipal';
}
