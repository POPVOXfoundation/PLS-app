<?php

use App\Domain\Documents\AssistantSourceDocument;
use App\Domain\Documents\Enums\AssistantSourceScope;
use App\Jobs\ExtractAssistantSourceText;
use App\Jobs\PollAssistantSourceTextExtraction;
use App\Support\PlsAssistant\AssistantSourceExtractionResult;
use App\Support\PlsAssistant\AssistantSourceTextExtractor;
use App\Support\PlsAssistant\AssistantSourceTextExtractorFactory;
use App\Support\PlsAssistant\ReviewAssistantGroundingRepository;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

test('extract assistant source text job updates content and makes the source available for grounding', function () {
    Storage::fake('assistant-sources');
    Storage::disk('assistant-sources')->put('imports/wfd/guide.pdf', 'pdf placeholder');

    config()->set('pls_assistant.assistant_sources.extractor', 'local');
    config()->set('pls_assistant.assistant_sources.pdftotext_binary', 'pdftotext');

    $document = AssistantSourceDocument::factory()->create([
        'title' => 'WFD Manual',
        'scope' => AssistantSourceScope::Global,
        'summary' => 'Use a documented workflow for post-legislative scrutiny.',
        'content' => null,
        'storage_path' => 'imports/wfd/guide.pdf',
        'metadata' => [
            'disk' => 'assistant-sources',
            'extraction' => [
                'status' => 'pending',
                'driver' => 'local',
            ],
        ],
    ]);

    Process::fake([
        '*pdftotext*' => Process::result(output: "Main steps in a PLS inquiry\n\n14\n\nPublish the report and track follow-up"),
    ]);

    (new ExtractAssistantSourceText($document->id))->handle(app(AssistantSourceTextExtractorFactory::class));

    $document->refresh();

    expect($document->content)->toContain('Main steps in a PLS inquiry')
        ->toContain('Publish the report and track follow-up')
        ->not->toContain("\n14\n")
        ->and(data_get($document->metadata, 'extraction.status'))->toBe('completed')
        ->and(data_get($document->metadata, 'extraction.driver'))->toBe('local');

    $review = plsReview([
        'title' => 'Grounding smoke review',
    ]);

    $globalGrounding = app(ReviewAssistantGroundingRepository::class)
        ->forPrompt($review->fresh(), 'What are the main steps in a PLS inquiry?')['global'];

    expect($globalGrounding)->toHaveCount(1)
        ->and($globalGrounding[0]['label'])->toBe('WFD Manual');
});

test('poll assistant source extraction job requeues when textract is still processing', function () {
    Queue::fake();

    $document = AssistantSourceDocument::factory()->create([
        'metadata' => [
            'disk' => 'assistant-s3',
            'extraction' => [
                'status' => 'processing',
                'driver' => 'textract',
                'textract_job_id' => 'job-123',
                'poll_attempts' => 0,
            ],
        ],
    ]);

    $extractor = new class implements AssistantSourceTextExtractor
    {
        public function extract(AssistantSourceDocument $document): AssistantSourceExtractionResult
        {
            return AssistantSourceExtractionResult::processing(
                driver: 'textract',
                method: 'aws-textract document text detection',
                metadata: [
                    'textract_job_id' => 'job-123',
                ],
                pollAfterSeconds: 9,
            );
        }
    };

    $factory = Mockery::mock(AssistantSourceTextExtractorFactory::class);
    $factory->shouldReceive('make')->once()->andReturn($extractor);

    (new PollAssistantSourceTextExtraction($document->id))->handle($factory);

    $document->refresh();

    expect(data_get($document->metadata, 'extraction.status'))->toBe('processing')
        ->and(data_get($document->metadata, 'extraction.poll_attempts'))->toBe(1)
        ->and(data_get($document->metadata, 'extraction.textract_job_id'))->toBe('job-123');

    Queue::assertPushed(PollAssistantSourceTextExtraction::class, function (PollAssistantSourceTextExtraction $job) use ($document): bool {
        return $job->assistantSourceDocumentId === $document->id;
    });
});

test('poll assistant source extraction job finalizes completed textract content without requeueing', function () {
    Queue::fake();

    $document = AssistantSourceDocument::factory()->create([
        'content' => null,
        'metadata' => [
            'disk' => 'assistant-s3',
            'extraction' => [
                'status' => 'processing',
                'driver' => 'textract',
                'textract_job_id' => 'job-123',
                'poll_attempts' => 0,
            ],
        ],
    ]);

    $extractor = new class implements AssistantSourceTextExtractor
    {
        public function extract(AssistantSourceDocument $document): AssistantSourceExtractionResult
        {
            return AssistantSourceExtractionResult::completed(
                driver: 'textract',
                method: 'aws-textract document text detection',
                content: 'Tracked follow-up and committee response.',
                metadata: [
                    'textract_job_id' => 'job-123',
                ],
            );
        }
    };

    $factory = Mockery::mock(AssistantSourceTextExtractorFactory::class);
    $factory->shouldReceive('make')->once()->andReturn($extractor);

    (new PollAssistantSourceTextExtraction($document->id))->handle($factory);

    $document->refresh();

    expect($document->content)->toBe('Tracked follow-up and committee response.')
        ->and(data_get($document->metadata, 'extraction.status'))->toBe('completed')
        ->and(data_get($document->metadata, 'extraction.poll_attempts'))->toBe(1);

    Queue::assertNotPushed(PollAssistantSourceTextExtraction::class);
});

test('poll assistant source extraction job marks the document failed after too many polling attempts', function () {
    config()->set('pls_assistant.assistant_sources.textract.max_poll_attempts', 1);

    $document = AssistantSourceDocument::factory()->create([
        'metadata' => [
            'disk' => 'assistant-s3',
            'extraction' => [
                'status' => 'processing',
                'driver' => 'textract',
                'textract_job_id' => 'job-123',
                'poll_attempts' => 1,
            ],
        ],
    ]);

    $factory = Mockery::mock(AssistantSourceTextExtractorFactory::class);
    $factory->shouldNotReceive('make');

    (new PollAssistantSourceTextExtraction($document->id))->handle($factory);

    $document->refresh();

    expect(data_get($document->metadata, 'extraction.status'))->toBe('failed')
        ->and(data_get($document->metadata, 'extraction.error'))->toContain('maximum attempts');
});
