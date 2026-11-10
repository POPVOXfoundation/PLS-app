<?php

namespace App\Jobs;

use App\Domain\Documents\Actions\AnalyzeReviewDocument;
use App\Domain\Documents\Actions\ChunkDocumentText;
use App\Domain\Documents\Actions\PersistReviewDocumentState;
use App\Domain\Documents\Actions\RecordDocumentExtraction;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

class ProcessReviewDocument implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public bool $failOnTimeout = true;

    public ?string $runId = null;

    public function __construct(public int $documentId, public string $step = 'extract')
    {
        $this->runId = data_get(Document::query()->find($documentId)?->metadata, 'extraction.run_id');
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

        if (! $document instanceof Document || $document->review === null || ! $this->isCurrentRun($document)) {
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
        $document = app(RecordDocumentExtraction::class)->forRun($this->documentId, $this->runId, function (Document $current) use ($state): Document {
            $wasAlreadyProcessing = data_get($current->metadata, 'extraction.status') === 'processing';
            $current = $state->markExtractingText($current);

            return $wasAlreadyProcessing ? $state->incrementPollAttempts($current) : $current;
        });
        if (! $document instanceof Document) {
            return;
        }
        $result = $analyze->extract($document);
        app(RecordDocumentExtraction::class)->forRun($this->documentId, $this->runId, function (Document $current) use ($result, $state, $chunkDocumentText): void {
            if (($result['status'] ?? null) !== 'completed') {
                $this->persistPendingExtraction($current, $result, $state);

                return;
            }
            $rawText = trim((string) ($result['raw_text'] ?? ''));
            if ($rawText !== '') {
                $chunkDocumentText->chunk($current, $rawText);
            }
            app(RecordDocumentExtraction::class)->record($current, $result);
            $state->markFillingRecord($current->fresh());
            $this->queueNext($current, 'enrich', (int) config('pls_assistant.review_source_enrichment.ai_stage_delay_seconds', 3));
        });
    }

    private function handleEnrichment(
        Document $document,
        AnalyzeReviewDocument $analyze,
        PersistReviewDocumentState $state,
    ): void {
        $extraction = $analyze->extract($document);

        if (($extraction['status'] ?? null) !== 'completed') {
            app(RecordDocumentExtraction::class)->forRun($this->documentId, $this->runId, function (Document $current) use ($extraction, $state): void {
                $this->persistPendingExtraction($current, $extraction, $state);
            });

            return;
        }

        $result = $analyze->enrich($document, (string) ($extraction['raw_text'] ?? ''), [
            'extraction_driver' => $extraction['extraction_driver'] ?? null,
            'extraction_method' => $extraction['extraction_method'] ?? null,
            'extraction_metadata' => $extraction['extraction_metadata'] ?? [],
            'poll_after_seconds' => $extraction['poll_after_seconds'] ?? null,
        ]);

        app(RecordDocumentExtraction::class)->forRun($this->documentId, $this->runId, function (Document $current) use ($state, $result): void {
            $state->persistResult($current, $result);
        });
    }

    /** @param array<string, mixed> $result */
    private function persistPendingExtraction(Document $document, array $result, PersistReviewDocumentState $state): void
    {
        if (($result['status'] ?? null) === 'processing') {
            $result['progress_stage'] = 'extracting_text';
        }
        $state->persistResult($document, $result);
        if (($result['status'] ?? null) === 'processing') {
            $this->queueNext($document, 'extract', (int) (($result['poll_after_seconds'] ?? null) ?: 15));
        }
    }

    private function queueNext(Document $document, string $step, int $delay): void
    {
        $job = new self($document->id, $step);
        $job->runId = $this->runId;
        $job->delay(now()->addSeconds($delay));
        DB::afterCommit(fn () => Bus::dispatch($job));
    }

    private function isCurrentRun(Document $document): bool
    {
        return data_get($document->metadata, 'extraction.run_id') === $this->runId;
    }

    public function failed(?\Throwable $exception): void
    {
        app(RecordDocumentExtraction::class)->fail($this->documentId, $this->runId, 'document_analysis');
    }

    public function backoff(): array
    {
        return [15, 30, 60];
    }
}
