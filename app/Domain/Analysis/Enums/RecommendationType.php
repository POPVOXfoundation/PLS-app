<?php

namespace App\Domain\Analysis\Enums;

enum RecommendationType: string
{
    case AmendLegislation = 'amend_legislation';
    case ImproveImplementation = 'improve_implementation';
    case OversightAction = 'oversight_action';
    case RequestMoreData = 'request_more_data';
}
