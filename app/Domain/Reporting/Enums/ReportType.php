<?php

namespace App\Domain\Reporting\Enums;

enum ReportType: string
{
    case DraftReport = 'draft_report';
    case FinalReport = 'final_report';
    case BriefingNote = 'briefing_note';
    case PublicSummary = 'public_summary';
}
