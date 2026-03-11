<?php

namespace App\Domain\Stakeholders\Enums;

enum StakeholderType: string
{
    case Ministry = 'ministry';
    case GovernmentAgency = 'government_agency';
    case Ngo = 'ngo';
    case Academic = 'academic';
    case Expert = 'expert';
    case IndustryGroup = 'industry_group';
    case CitizenGroup = 'citizen_group';
}
