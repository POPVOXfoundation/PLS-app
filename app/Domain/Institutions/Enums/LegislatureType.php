<?php

namespace App\Domain\Institutions\Enums;

enum LegislatureType: string
{
    case Parliament = 'parliament';
    case Congress = 'congress';
    case Assembly = 'assembly';
    case Legislature = 'legislature';
}
