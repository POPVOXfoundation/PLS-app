<?php

namespace App\Domain\Documents\Actions;

use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Legislation\Actions\PersistLegislationSourceState;
use App\Jobs\ProcessReviewDocument;
use App\Jobs\ProcessReviewLegislationSource;
use Illuminate\Support\Facades\Bus;
use Throwable;

class QueueReviewSourceProcessing
{
    public function queue(Document $document, bool $retry = false): void
    {
        $legislation = $document->document_type === DocumentType::LegislationText;
        $state = $legislation ? app(PersistLegislationSourceState::class) : app(PersistReviewDocumentState::class);
        $retry ? $state->resetForRetry($document) : $state->markQueued($document);
        $job = $legislation ? new ProcessReviewLegislationSource($document->id) : new ProcessReviewDocument($document->id);
        try {
            Bus::dispatch($job);
        } catch (Throwable $exception) {
            report($exception);
            $job->failed($exception);
        }
    }
}
