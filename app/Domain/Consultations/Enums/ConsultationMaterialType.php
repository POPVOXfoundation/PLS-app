<?php

namespace App\Domain\Consultations\Enums;

enum ConsultationMaterialType: string
{
    case HearingTranscript = 'hearing_transcript';
    case InterviewTranscript = 'interview_transcript';
    case FocusGroupNotes = 'focus_group_notes';
    case SurveyResults = 'survey_results';
    case WrittenSubmission = 'written_submission';
    case PublicComments = 'public_comments';
    case MeetingNotes = 'meeting_notes';
    case Other = 'other';
}
