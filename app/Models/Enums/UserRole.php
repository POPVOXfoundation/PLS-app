<?php

namespace App\Models\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case CommitteeStaff = 'committee_staff';
    case Reviewer = 'reviewer';
    case Observer = 'observer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::CommitteeStaff => 'Committee Staff',
            self::Reviewer => 'Reviewer',
            self::Observer => 'Observer',
        };
    }
}
