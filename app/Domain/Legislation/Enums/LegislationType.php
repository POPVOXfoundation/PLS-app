<?php

namespace App\Domain\Legislation\Enums;

enum LegislationType: string
{
    case Act = 'act';
    case Law = 'law';
    case Statute = 'statute';
    case Regulation = 'regulation';
    case Ordinance = 'ordinance';
}
