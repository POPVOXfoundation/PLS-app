<?php

namespace App\Jobs;

use App\Domain\Documents\AssistantSourceDocument;
use App\Support\PlsAssistant\AssistantSourceExtractionResult;
use App\Support\PlsAssistant\AssistantSourceTextExtractorFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class PollAssistantSourceTextExtraction implements ShouldQueue
{
    use Queueable;

    public int $tries = 20;

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

        $pollAttempts = (int) data_get($document->metadata, 'extraction.poll_attempts', 0) + 1;
        $maxPollAttempts = (int) config('pls_assistant.assistant_sources.textract.max_poll_attempts', 20);

        if ($pollAttempts > $maxPollAttempts) {
            $metadata = $document->metadata ?? [];
            data_set($metadata, 'extraction.status', 'failed');
            data_set($metadata, 'extraction.driver', data_get($metadata, 'extraction.driver', 'textract'));
            data_set($metadata, 'extraction.error', 'Textract polling exceeded the configured maximum attempts.');
            data_set($metadata, 'extraction.failed_at', now()->toIso8601String());
            data_set($metadata, 'extraction.poll_attempts', $pollAttempts);

            $document->forceFill([
                'metadata' => $metadata,
            ])->save();

            return;
        }

        $result = $factory->make()->extract($document);

        $this->persistResult($document, $result, $pollAttempts);

        if ($result->status === 'processing') {
            self::dispatch($document->getKey())
                ->delay(now()->addSeconds($result->pollAfterSeconds ?? 15));
        }
    }

    public function backoff(): array
    {
        return [15, 30, 60];
    }

    private function persistResult(
        AssistantSourceDocument $document,
        AssistantSourceExtractionResult $result,
        int $pollAttempts,
    ): void {
        $metadata = $document->metadata ?? [];

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
