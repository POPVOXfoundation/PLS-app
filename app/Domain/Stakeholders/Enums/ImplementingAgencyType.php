<?php

namespace App\Domain\Stakeholders\Enums;

enum ImplementingAgencyType: string
{
    case Ministry = 'ministry';
    case Department = 'department';
    case Agency = 'agency';
    case Regulator = 'regulator';
    case Authority = 'authority';
    case Secretariat = 'secretariat';
}
