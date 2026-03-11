<?php

namespace App\Domain\Consultations\Enums;

enum ConsultationType: string
{
    case Hearing = 'hearing';
    case Roundtable = 'roundtable';
    case Interview = 'interview';
    case PublicConsultation = 'public_consultation';
    case Workshop = 'workshop';
}
