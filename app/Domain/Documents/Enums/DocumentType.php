<?php

namespace App\Domain\Documents\Enums;

enum DocumentType: string
{
    case LegislationText = 'legislation_text';
    case CommitteeReport = 'committee_report';
    case ImplementationReport = 'implementation_report';
    case ConsultationSubmission = 'consultation_submission';
    case HearingTranscript = 'hearing_transcript';
    case PolicyReport = 'policy_report';
    case GovernmentResponse = 'government_response';
    case DraftReport = 'draft_report';
    case FinalReport = 'final_report';
}
