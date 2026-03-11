<?php

namespace App\Domain\Legislation\Enums;

enum ReviewLegislationRelationshipType: string
{
    case Primary = 'primary';
    case Related = 'related';
    case Delegated = 'delegated';
}
