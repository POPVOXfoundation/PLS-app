<?php

namespace App\Domain\Legislation\Enums;

enum LegislationType: string
{
    case Bill = 'bill';
    case ParliamentaryDebate = 'parliamentary_debate';
    case Amendment = 'amendment';
    case Unclassified = 'unclassified';
    case Act = 'act';
    case Law = 'law';
    case Statute = 'statute';
    case Regulation = 'regulation';
    case Ordinance = 'ordinance';
}
