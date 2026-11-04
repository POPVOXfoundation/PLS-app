<?php

namespace App\Http\Controllers\Pls;

use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Reviews\PlsReview;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReviewLegislationSourceOriginalController extends ReviewDocumentOriginalController
{
    public function __invoke(PlsReview $review, Document $document): StreamedResponse
    {
        Gate::authorize('view', $review);
        abort_unless($document->document_type === DocumentType::LegislationText, 404);

        return parent::__invoke($review, $document);
    }
}
