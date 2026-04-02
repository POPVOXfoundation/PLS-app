<?php

namespace App\Jobs;

use App\Domain\Documents\Actions\AnalyzeReviewDocument;
use App\Domain\Documents\Actions\ChunkDocumentText;
use App\Domain\Documents\Actions\PersistReviewDocumentState;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessReviewDocument implements ShouldQueue
{
    use Queueable;

    public int $tries = 20;

    public function __construct(public int $documentId, public string $step = 'extract')
    {
        $this->onConnection('redis');
        $this->onQueue((string) config('pls_assistant.review_source_enrichment.queue', 'review-source-enrichment'));
    }

    public function handle(
        AnalyzeReviewDocument $analyze,
        PersistReviewDocumentState $state,
        ChunkDocumentText $chunkDocumentText,
    ): void {
        $document = Document::query()
            ->with('review')
            ->where('document_type', '!=', DocumentType::LegislationText->value)
            ->find($this->documentId);

        if (! $document instanceof Document || $document->review === null) {
            return;
        }

        if ($this->step === 'enrich') {
            $this->handleEnrichment($document, $analyze, $state);

            return;
        }

        $this->handleExtraction($document, $analyze, $state, $chunkDocumentText);
    }

    private function handleExtraction(
        Document $document,
        AnalyzeReviewDocument $analyze,
        PersistReviewDocumentState $state,
        ChunkDocumentText $chunkDocumentText,
    ): void {
        $wasAlreadyProcessing = (string) data_get($document->metadata, 'extraction.status') === 'processing';
        $document = $state->markExtractingText($document);

        if ($wasAlreadyProcessing) {
            $document = $state->incrementPollAttempts($document);
        }

        $result = $analyze->extract($document);

        if (($result['status'] ?? null) !== 'completed') {
            if (($result['status'] ?? null) === 'processing') {
                $result['progress_stage'] = 'extracting_text';
            }

            $state->persistResult($document, $result);

            if (($result['status'] ?? null) === 'processing') {
                self::dispatch($document->id, 'extract')
                    ->delay(now()->addSeconds((int) (($result['poll_after_seconds'] ?? null) ?: 15)));
            }

            return;
        }

        $rawText = trim((string) ($result['raw_text'] ?? ''));

        if ($rawText !== '') {
            $chunkDocumentText->chunk($document->fresh(), $rawText);
        }

        $state->markFillingRecord($document->fresh());

        self::dispatch($document->id, 'enrich')
            ->delay(now()->addSeconds((int) config('pls_assistant.review_source_enrichment.ai_stage_delay_seconds', 3)));
    }

    private function handleEnrichment(
        Document $document,
        AnalyzeReviewDocument $analyze,
        PersistReviewDocumentState $state,
    ): void {
        $extraction = $analyze->extract($document);

        if (($extraction['status'] ?? null) !== 'completed') {
            $state->persistResult($document, $extraction);

            if (($extraction['status'] ?? null) === 'processing') {
                self::dispatch($document->id, 'extract')
                    ->delay(now()->addSeconds((int) (($extraction['poll_after_seconds'] ?? null) ?: 15)));
            }

            return;
        }

        $result = $analyze->enrich($document, (string) ($extraction['raw_text'] ?? ''), [
            'extraction_driver' => $extraction['extraction_driver'] ?? null,
            'extraction_method' => $extraction['extraction_method'] ?? null,
            'extraction_metadata' => $extraction['extraction_metadata'] ?? [],
            'poll_after_seconds' => $extraction['poll_after_seconds'] ?? null,
        ]);

        $state->persistResult($document, $result);
    }

    public function backoff(): array
    {
        return [15, 30, 60];
    }
}
