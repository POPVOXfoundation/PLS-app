<?php

namespace App\Domain\Consultations\Enums;

enum ConsultationType: string
{
    case Hearing = 'hearing';
    case Roundtable = 'roundtable';
    case Interview = 'interview';
    case FocusGroup = 'focus_group';
    case Survey = 'survey';
    case PublicConsultation = 'public_consultation';
    case PublicCommentPeriod = 'public_comment_period';
    case Workshop = 'workshop';
}
