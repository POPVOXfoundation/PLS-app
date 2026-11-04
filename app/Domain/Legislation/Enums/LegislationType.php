<?php

namespace App\Domain\Legislation\Enums;

enum LegislationType: string
{
    case Bill = 'bill';
    case ParliamentaryRecord = 'parliamentary_record';
    case Act = 'act';
    case Law = 'law';
    case Statute = 'statute';
    case Regulation = 'regulation';
    case Ordinance = 'ordinance';
}
