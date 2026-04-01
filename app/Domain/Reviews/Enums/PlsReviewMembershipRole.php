<?php

namespace App\Domain\Reviews\Enums;

enum PlsReviewMembershipRole: string
{
    case Owner = 'owner';
    case Contributor = 'contributor';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Owner',
            self::Contributor => 'Contributor',
            self::Viewer => 'Viewer',
        };
    }
}
