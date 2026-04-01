<?php

namespace App\Jobs;

use App\Domain\Documents\AssistantSourceDocument;
use App\Support\PlsAssistant\AssistantSourceExtractionResult;
use App\Support\PlsAssistant\AssistantSourceTextExtractorFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ExtractAssistantSourceText implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public int $assistantSourceDocumentId,
    ) {
        $this->onQueue('assistant-sources');
    }

    public function handle(AssistantSourceTextExtractorFactory $factory): void
    {
        $document = AssistantSourceDocument::query()->find($this->assistantSourceDocumentId);

        if (! $document instanceof AssistantSourceDocument) {
            return;
        }

        $result = $factory->make()->extract($document);

        $this->persistResult($document, $result);

        if ($result->status === 'processing') {
            PollAssistantSourceTextExtraction::dispatch($document->getKey())
                ->delay(now()->addSeconds($result->pollAfterSeconds ?? 15));
        }
    }

    public function backoff(): array
    {
        return [10, 30, 60];
    }

    private function persistResult(AssistantSourceDocument $document, AssistantSourceExtractionResult $result): void
    {
        $metadata = $document->metadata ?? [];
        $pollAttempts = (int) data_get($metadata, 'extraction.poll_attempts', 0);

        foreach ($result->metadata as $key => $value) {
            data_set($metadata, "extraction.{$key}", $value);
        }

        data_set($metadata, 'extraction.status', $result->status);
        data_set($metadata, 'extraction.driver', $result->driver);
        data_set($metadata, 'extraction.poll_attempts', $pollAttempts);
        data_set($metadata, 'extraction.error', $result->error);
        data_set($metadata, 'extraction_method', $result->method);

        if ($result->status === 'processing') {
            data_set($metadata, 'extraction.processing_at', now()->toIso8601String());
        }

        if ($result->status === 'completed') {
            data_set($metadata, 'extraction.completed_at', now()->toIso8601String());
        }

        if ($result->status === 'failed') {
            data_set($metadata, 'extraction.failed_at', now()->toIso8601String());
        }

        $document->forceFill([
            'content' => $result->content ?? $document->content,
            'metadata' => $metadata,
        ])->save();
    }
}
