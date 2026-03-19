<?php

namespace App\Models\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case ReviewGroupStaff = 'review_group_staff';
    case Reviewer = 'reviewer';
    case Observer = 'observer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Admin',
            self::ReviewGroupStaff => 'Review Group Staff',
            self::Reviewer => 'Reviewer',
            self::Observer => 'Observer',
        };
    }
}
