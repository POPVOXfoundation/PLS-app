<?php

namespace App\Domain\Analysis\Enums;

enum EvidenceType: string
{
    case Documentary = 'documentary';
    case Statistical = 'statistical';
    case Testimony = 'testimony';
    case Consultation = 'consultation';
    case Analysis = 'analysis';
}
