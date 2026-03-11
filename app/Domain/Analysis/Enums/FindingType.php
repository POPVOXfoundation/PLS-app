<?php

namespace App\Domain\Analysis\Enums;

enum FindingType: string
{
    case ImplementationGap = 'implementation_gap';
    case EffectivenessIssue = 'effectiveness_issue';
    case UnintendedConsequence = 'unintended_consequence';
    case ComplianceProblem = 'compliance_problem';
    case AdministrativeIssue = 'administrative_issue';
}
