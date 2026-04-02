<?php

use App\Ai\Agents\LegislationSourceExtractorAgent;
use App\Ai\Agents\ReviewDocumentExtractorAgent;
use App\Domain\Analysis\Enums\FindingType;
use App\Domain\Analysis\Enums\RecommendationType;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Legislation\Enums\LegislationType;
use App\Domain\Legislation\Enums\ReviewLegislationRelationshipType;
use App\Domain\Legislation\Legislation;
use App\Domain\Reporting\Enums\GovernmentResponseStatus;
use App\Domain\Reporting\Enums\ReportStatus;
use App\Domain\Reporting\Enums\ReportType;
use App\Domain\Reporting\Report;
use App\Jobs\ProcessReviewDocument;
use App\Jobs\ProcessReviewLegislationSource;
use App\Livewire\Pls\Reviews\AnalysisPage;
use App\Livewire\Pls\Reviews\DocumentsPage;
use App\Livewire\Pls\Reviews\LegislationPage;
use App\Livewire\Pls\Reviews\ReportsPage;
use App\Livewire\Pls\Reviews\WorkflowPage;
use App\Models\User;
use App\Support\PlsAssistant\AssistantSourceExtractionResult;
use App\Support\PlsAssistant\AssistantSourceTextExtractor;
use App\Support\PlsAssistant\AssistantSourceTextExtractorFactory;
use App\Support\Toast;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function runLegislationSourcePipeline(int $documentId): void
{
    app()->call([(new ProcessReviewLegislationSource($documentId)), 'handle']);
    app()->call([(new ProcessReviewLegislationSource($documentId, 'enrich')), 'handle']);
}

function runReviewDocumentPipeline(int $documentId): void
{
    app()->call([(new ProcessReviewDocument($documentId)), 'handle']);
    app()->call([(new ProcessReviewDocument($documentId, 'enrich')), 'handle']);
}

test('uploaded legislation sources are queued and can be saved as new legislation after background processing', function () {
    Storage::fake('s3');
    Queue::fake();
    config()->set('pls_assistant.assistant_sources.extractor', 'textract');
    config()->set('pls_assistant.assistant_sources.source_disk', 's3');

    $review = plsReview([
        'title' => 'Review of access to information implementation',
    ]);

    $extractor = new class implements AssistantSourceTextExtractor
    {
        public function extract(\App\Domain\Documents\AssistantSourceDocument|\App\Domain\Documents\Document $document): AssistantSourceExtractionResult
        {
            return AssistantSourceExtractionResult::completed(
                driver: 'stub',
                method: 'stubbed shared extractor',
                content: <<<'TEXT'
                Access to Information Act
                Short title: ATI Act

                This Act establishes a public right of access to government information. It sets timelines for responses and permits regulations to support implementation.

                Enacted on May 4, 2010.
                TEXT,
            );
        }
    };

    $factory = Mockery::mock(AssistantSourceTextExtractorFactory::class);
    $factory->shouldReceive('make')->once()->andReturn($extractor);
    app()->instance(AssistantSourceTextExtractorFactory::class, $factory);
    LegislationSourceExtractorAgent::fake([[
        'title' => 'Access to Information Act',
        'short_title' => 'ATI Act',
        'legislation_type' => LegislationType::Act->value,
        'date_enacted' => '2010-05-04',
        'summary' => 'Establishes a public right of access to government information and supports implementation through regulations.',
        'key_themes' => ['Public access rights', 'Response timelines'],
        'notable_excerpts' => ['This Act establishes a public right of access to government information.'],
        'important_dates' => ['2010-05-04'],
        'relationship_type' => ReviewLegislationRelationshipType::Primary->value,
        'warnings' => [],
    ]]);

    $component = Livewire::test(LegislationPage::class, ['review' => $review])
        ->set('sourceUpload', UploadedFile::fake()->create('access-to-information-act.pdf', 256, 'application/pdf'))
        ->assertSee('Processing')
        ->assertDontSee('Review record');

    $document = $review->fresh()->documents()->sole();

    Queue::assertPushed(ProcessReviewLegislationSource::class, fn (ProcessReviewLegislationSource $job): bool => $job->documentId === $document->id && $job->step === 'extract');

    $component
        ->call('refreshPendingAnalyses')
        ->assertSee('Processing');

    runLegislationSourcePipeline($document->id);

    $component
        ->call('refreshPendingAnalyses')
        ->assertSee('Needs review')
        ->assertSee('Access to Information Act')
        ->call('startReviewDocument', $document->id)
        ->assertSet('analysisTitle', 'Access to Information Act')
        ->assertSet('analysisShortTitle', 'ATI Act')
        ->assertSet('analysisType', LegislationType::Act->value)
        ->assertSet('analysisDateEnacted', '2010-05-04')
        ->assertSet('analysisRelationshipType', ReviewLegislationRelationshipType::Primary->value)
        ->assertSee('Review record')
        ->assertSee('Public access rights')
        ->assertSee('Response timelines')
        ->assertSee('This Act establishes a public right of access to government information.')
        ->assertSee('2010-05-04')
        ->call('saveAnalyzedLegislation')
        ->assertHasNoErrors()
        ->assertSee('Access to Information Act');

    $legislation = Legislation::query()->where('title', 'Access to Information Act')->firstOrFail();

    expect($legislation->jurisdiction_id)->toBe($review->jurisdiction_id)
        ->and($legislation->source_document_id)->toBe($document->id);

    $this->assertDatabaseHas('pls_review_legislation', [
        'pls_review_id' => $review->id,
        'legislation_id' => $legislation->id,
        'relationship_type' => ReviewLegislationRelationshipType::Primary->value,
    ]);
});

test('oversized legislation uploads are rejected', function () {
    config()->set('pls_assistant.assistant_sources.extractor', 'textract');
    config()->set('pls_assistant.assistant_sources.source_disk', 's3');

    $review = plsReview([
        'title' => 'Review of oversized uploads',
    ]);

    Livewire::test(LegislationPage::class, ['review' => $review])
        ->set('sourceUpload', UploadedFile::fake()->create('too-large.pdf', 51201, 'application/pdf'))
        ->assertHasErrors(['sourceUpload']);

    expect($review->fresh()->documents)->toHaveCount(0);
});

test('legislation source rows can be deleted from records', function () {
    Storage::fake('s3');
    Queue::fake();
    config()->set('pls_assistant.assistant_sources.extractor', 'textract');
    config()->set('pls_assistant.assistant_sources.source_disk', 's3');

    $review = plsReview([
        'title' => 'Review of removable source rows',
    ]);

    $extractor = new class implements AssistantSourceTextExtractor
    {
        public function extract(\App\Domain\Documents\AssistantSourceDocument|\App\Domain\Documents\Document $document): AssistantSourceExtractionResult
        {
            return AssistantSourceExtractionResult::completed(
                driver: 'stub',
                method: 'stubbed shared extractor',
                content: 'Implementation note with no clear enactment details or formal heading.',
            );
        }
    };

    $factory = Mockery::mock(AssistantSourceTextExtractorFactory::class);
    $factory->shouldReceive('make')->once()->andReturn($extractor);
    app()->instance(AssistantSourceTextExtractorFactory::class, $factory);
    LegislationSourceExtractorAgent::fake([[
        'title' => 'Implementation Note',
        'short_title' => null,
        'legislation_type' => LegislationType::Act->value,
        'date_enacted' => null,
        'summary' => null,
        'relationship_type' => ReviewLegislationRelationshipType::Primary->value,
        'warnings' => ['The summary needs manual review because the extracted source text was limited.'],
    ]]);

    $component = Livewire::test(LegislationPage::class, ['review' => $review])
        ->set('sourceUpload', UploadedFile::fake()->create('working-note.pdf', 128, 'application/pdf'))
        ->assertSee('Processing');

    $document = $review->fresh()->documents()->sole();

    runLegislationSourcePipeline($document->id);

    $component
        ->call('refreshPendingAnalyses')
        ->assertSee('Implementation Note');

    Storage::disk('s3')->assertExists($document->storage_path);

    $component
        ->call('confirmDeletion', $document->id)
        ->assertDontSee('Implementation Note');

    $this->assertDatabaseMissing('documents', [
        'id' => $document->id,
    ]);

    Storage::disk('s3')->assertMissing($document->storage_path);
});

test('deleting a saved source row removes the saved legislation from the current review records', function () {
    Storage::fake('s3');
    Queue::fake();
    config()->set('pls_assistant.assistant_sources.extractor', 'textract');
    config()->set('pls_assistant.assistant_sources.source_disk', 's3');

    $review = plsReview([
        'title' => 'Review of deleting saved source rows',
    ]);

    $extractor = new class implements AssistantSourceTextExtractor
    {
        public function extract(\App\Domain\Documents\AssistantSourceDocument|\App\Domain\Documents\Document $document): AssistantSourceExtractionResult
        {
            return AssistantSourceExtractionResult::completed(
                driver: 'stub',
                method: 'stubbed shared extractor',
                content: <<<'TEXT'
                Southern Deep Port Development Facility Act, 2024
                Short title: Deep Port Act

                This Act provides exemptions from taxes and duties for the facility and authorizes related implementation rules.

                Enacted on April 2, 2026.
                TEXT,
            );
        }
    };

    $factory = Mockery::mock(AssistantSourceTextExtractorFactory::class);
    $factory->shouldReceive('make')->once()->andReturn($extractor);
    app()->instance(AssistantSourceTextExtractorFactory::class, $factory);
    LegislationSourceExtractorAgent::fake([[
        'title' => 'Southern Deep Port Development Facility Act, 2024',
        'short_title' => 'Deep Port Act',
        'legislation_type' => LegislationType::Act->value,
        'date_enacted' => '2026-04-02',
        'summary' => 'Provides exemptions from taxes and duties for the facility and authorizes related implementation rules.',
        'key_themes' => ['Tax and duty exemptions', 'Implementation rules'],
        'notable_excerpts' => ['This Act provides exemptions from taxes and duties for the facility.'],
        'important_dates' => ['2026-04-02'],
        'relationship_type' => ReviewLegislationRelationshipType::Primary->value,
        'warnings' => [],
    ]]);

    $component = Livewire::test(LegislationPage::class, ['review' => $review])
        ->set('sourceUpload', UploadedFile::fake()->create('deep-port-act.pdf', 128, 'application/pdf'));

    $document = $review->fresh()->documents()->sole();

    runLegislationSourcePipeline($document->id);

    $component
        ->call('refreshPendingAnalyses')
        ->call('startReviewDocument', $document->id)
        ->call('saveAnalyzedLegislation')
        ->assertSee('Saved')
        ->assertSee('Southern Deep Port Development Facility Act, 2024');

    $legislation = Legislation::query()
        ->where('source_document_id', $document->id)
        ->firstOrFail();

    $component
        ->call('confirmDeletion', $document->id)
        ->assertDontSee('Southern Deep Port Development Facility Act, 2024');

    $this->assertDatabaseMissing('documents', [
        'id' => $document->id,
    ]);

    $this->assertDatabaseMissing('pls_review_legislation', [
        'pls_review_id' => $review->id,
        'legislation_id' => $legislation->id,
    ]);

    $this->assertDatabaseMissing('legislation', [
        'id' => $legislation->id,
    ]);

    Storage::disk('s3')->assertMissing($document->storage_path);
});

test('saved source rows can be reopened for editing after the initial review', function () {
    Storage::fake('s3');
    Queue::fake();
    config()->set('pls_assistant.assistant_sources.extractor', 'textract');
    config()->set('pls_assistant.assistant_sources.source_disk', 's3');

    $review = plsReview([
        'title' => 'Review of reopening saved source rows',
    ]);

    $extractor = new class implements AssistantSourceTextExtractor
    {
        public function extract(\App\Domain\Documents\AssistantSourceDocument|\App\Domain\Documents\Document $document): AssistantSourceExtractionResult
        {
            return AssistantSourceExtractionResult::completed(
                driver: 'stub',
                method: 'stubbed shared extractor',
                content: <<<'TEXT'
                Southern Deep Port Development Facility Act, 2024
                Short title: Deep Port Act

                This Act provides exemptions from taxes and duties for the facility and authorizes related implementation rules.

                Enacted on April 2, 2026.
                TEXT,
            );
        }
    };

    $factory = Mockery::mock(AssistantSourceTextExtractorFactory::class);
    $factory->shouldReceive('make')->once()->andReturn($extractor);
    app()->instance(AssistantSourceTextExtractorFactory::class, $factory);
    LegislationSourceExtractorAgent::fake([[
        'title' => 'Southern Deep Port Development Facility Act, 2024',
        'short_title' => 'Deep Port Act',
        'legislation_type' => LegislationType::Act->value,
        'date_enacted' => '2026-04-02',
        'summary' => 'Provides exemptions from taxes and duties for the facility and authorizes related implementation rules.',
        'key_themes' => ['Tax and duty exemptions', 'Implementation rules'],
        'notable_excerpts' => ['This Act provides exemptions from taxes and duties for the facility.'],
        'important_dates' => ['2026-04-02'],
        'relationship_type' => ReviewLegislationRelationshipType::Primary->value,
        'warnings' => [],
    ]]);

    $component = Livewire::test(LegislationPage::class, ['review' => $review])
        ->set('sourceUpload', UploadedFile::fake()->create('deep-port-act.pdf', 128, 'application/pdf'));

    $document = $review->fresh()->documents()->sole();

    runLegislationSourcePipeline($document->id);

    $component
        ->call('refreshPendingAnalyses')
        ->call('startReviewDocument', $document->id)
        ->call('saveAnalyzedLegislation')
        ->assertSee('Saved')
        ->assertSee('Edit');

    $component
        ->call('startReviewDocument', $document->id)
        ->assertSet('analysisStatus', 'saved')
        ->assertSet('analysisSaveMode', 'update')
        ->assertSet('analysisTitle', 'Southern Deep Port Development Facility Act, 2024')
        ->assertSet('analysisExistingLegislationId', fn (string $value): bool => $value !== '')
        ->assertSee('Edit record')
        ->assertSee('Tax and duty exemptions')
        ->assertSee('This Act provides exemptions from taxes and duties for the facility.')
        ->assertSee('Save changes');
});

test('saved legislation rows show warnings without the needs review heading', function () {
    $review = plsReview([
        'title' => 'Review of saved legislation warnings',
    ]);

    $document = Document::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Public Records Act source',
        'document_type' => DocumentType::LegislationText,
        'metadata' => [
            'legislation_analysis' => [
                'status' => 'saved',
                'source_document_id' => null,
                'source_label' => 'Public Records Act source',
                'title' => 'Public Records Act',
                'short_title' => 'PRA',
                'legislation_type' => LegislationType::Act->value,
                'relationship_type' => ReviewLegislationRelationshipType::Primary->value,
                'warnings' => ['Double-check the enactment date against the gazette.'],
                'legislation_id' => null,
            ],
        ],
    ]);

    Livewire::test(LegislationPage::class, ['review' => $review])
        ->call('startReviewDocument', $document->id)
        ->assertSee('Warnings')
        ->assertDontSee('Needs review')
        ->assertSee('Double-check the enactment date against the gazette.');
});

test('uploaded legislation docx sources use the shared extractor and can be saved', function () {
    Storage::fake('s3');
    Queue::fake();

    config()->set('pls_assistant.assistant_sources.extractor', 'textract');

    $review = plsReview([
        'title' => 'Review of uploaded docx legislation',
    ]);

    $extractor = new class implements AssistantSourceTextExtractor
    {
        public function extract(\App\Domain\Documents\AssistantSourceDocument|\App\Domain\Documents\Document $document): AssistantSourceExtractionResult
        {
            expect($document)->toBeInstanceOf(Document::class)
                ->and(data_get($document->metadata, 'disk'))->toBe('s3');

            return AssistantSourceExtractionResult::completed(
                driver: 'stub',
                method: 'stubbed shared extractor',
                content: <<<'TEXT'
                Access to Information Act
                Short title: ATI Act

                This Act establishes a public right of access to government information.

                Enacted on May 4, 2010.
                TEXT,
            );
        }
    };

    $factory = Mockery::mock(AssistantSourceTextExtractorFactory::class);
    $factory->shouldReceive('make')->once()->andReturn($extractor);
    app()->instance(AssistantSourceTextExtractorFactory::class, $factory);
    LegislationSourceExtractorAgent::fake([[
        'title' => 'Access to Information Act',
        'short_title' => 'ATI Act',
        'legislation_type' => LegislationType::Act->value,
        'date_enacted' => '2010-05-04',
        'summary' => 'Establishes a public right of access to government information.',
        'relationship_type' => ReviewLegislationRelationshipType::Primary->value,
        'warnings' => [],
    ]]);

    $component = Livewire::test(LegislationPage::class, ['review' => $review])
        ->set('sourceUpload', UploadedFile::fake()->create(
            'access-to-information-act.docx',
            256,
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ))
        ->assertSee('Processing');

    $document = $review->fresh()->documents()->sole();

    runLegislationSourcePipeline($document->id);

    $component
        ->call('refreshPendingAnalyses')
        ->assertSee('Needs review')
        ->assertSee('Access to Information Act')
        ->call('startReviewDocument', $document->id)
        ->assertSet('analysisTitle', 'Access to Information Act')
        ->assertSet('analysisShortTitle', 'ATI Act')
        ->assertSet('analysisDateEnacted', '2010-05-04')
        ->assertSee('Review record')
        ->call('saveAnalyzedLegislation')
        ->assertHasNoErrors()
        ->assertSee('Access to Information Act');

    expect(data_get($document->metadata, 'disk'))->toBe('s3');
    Storage::disk('s3')->assertExists($document->storage_path);
});

test('bill-style source text is kept as a primary record and avoids structural summary garbage', function () {
    Storage::fake('s3');
    Queue::fake();
    config()->set('pls_assistant.assistant_sources.extractor', 'textract');
    config()->set('pls_assistant.assistant_sources.source_disk', 's3');

    $review = plsReview([
        'title' => 'Review of a bill-style source',
    ]);

    $extractor = new class implements AssistantSourceTextExtractor
    {
        public function extract(\App\Domain\Documents\AssistantSourceDocument|\App\Domain\Documents\Document $document): AssistantSourceExtractionResult
        {
            return AssistantSourceExtractionResult::completed(
                driver: 'stub',
                method: 'stubbed shared extractor',
                content: <<<'TEXT'
                BELIZE: SOUTHERN DEEP PORT DEVELOPMENT FACILITY BILL, 2024

                ARRANGEMENT OF CLAUSES
                1. Short title.
                2. Interpretation.

                A Bill for an Act to provide for the development and operation of a southern deep port facility and related matters.
                TEXT,
            );
        }
    };

    $factory = Mockery::mock(AssistantSourceTextExtractorFactory::class);
    $factory->shouldReceive('make')->once()->andReturn($extractor);
    app()->instance(AssistantSourceTextExtractorFactory::class, $factory);
    LegislationSourceExtractorAgent::fake([[
        'title' => 'Southern Deep Port Development Facility Bill, 2024',
        'short_title' => 'Southern Deep Port Development Facility Bill',
        'legislation_type' => LegislationType::Act->value,
        'date_enacted' => null,
        'summary' => 'Provides for the development and operation of a southern deep port facility and related matters.',
        'relationship_type' => ReviewLegislationRelationshipType::Primary->value,
        'warnings' => ['This looks like a bill or draft text, so an enactment date may not be available yet.'],
    ]]);

    $component = Livewire::test(LegislationPage::class, ['review' => $review])
        ->set('sourceUpload', UploadedFile::fake()->create('southern-deep-port-development-facility-bill-2024.pdf', 256, 'application/pdf'))
        ->assertSee('Processing');

    $document = $review->fresh()->documents()->sole();

    runLegislationSourcePipeline($document->id);

    $component
        ->call('refreshPendingAnalyses')
        ->call('startReviewDocument', $document->id)
        ->assertSet('analysisTitle', 'Southern Deep Port Development Facility Bill, 2024')
        ->assertSet('analysisShortTitle', 'Southern Deep Port Development Facility Bill')
        ->assertSet('analysisType', LegislationType::Act->value)
        ->assertSet('analysisRelationshipType', ReviewLegislationRelationshipType::Primary->value)
        ->assertSet('analysisSummary', fn (string $summary): bool => str_contains(strtolower($summary), 'deep port facility'))
        ->assertSee('This looks like a bill or draft text, so an enactment date may not be available yet.')
        ->assertDontSee('Ready');
});

test('processing legislation extraction requeues in the background until text is ready', function () {
    Storage::fake('s3');
    Queue::fake();
    config()->set('pls_assistant.assistant_sources.extractor', 'textract');
    config()->set('pls_assistant.assistant_sources.source_disk', 's3');

    $review = plsReview([
        'title' => 'Review of source processing state',
    ]);

    $extractor = new class implements AssistantSourceTextExtractor
    {
        public function extract(\App\Domain\Documents\AssistantSourceDocument|\App\Domain\Documents\Document $document): AssistantSourceExtractionResult
        {
            if (data_get($document->metadata, 'extraction.textract_job_id') === null) {
                return AssistantSourceExtractionResult::processing(
                    driver: 'stub',
                    method: 'stubbed shared extractor',
                    metadata: [
                        'textract_job_id' => 'job-123',
                    ],
                    pollAfterSeconds: 3,
                );
            }

            return AssistantSourceExtractionResult::completed(
                driver: 'stub',
                method: 'stubbed shared extractor',
                content: <<<'TEXT'
                Access to Information Act
                Short title: ATI Act

                This Act establishes a public right of access to government information.

                Enacted on May 4, 2010.
                TEXT,
            );
        }
    };

    $factory = Mockery::mock(AssistantSourceTextExtractorFactory::class);
    $factory->shouldReceive('make')->times(3)->andReturn($extractor);
    app()->instance(AssistantSourceTextExtractorFactory::class, $factory);
    LegislationSourceExtractorAgent::fake([[
        'title' => 'Access to Information Act',
        'short_title' => 'ATI Act',
        'legislation_type' => LegislationType::Act->value,
        'date_enacted' => '2010-05-04',
        'summary' => 'Establishes a public right of access to government information.',
        'relationship_type' => ReviewLegislationRelationshipType::Primary->value,
        'warnings' => [],
    ]]);

    Livewire::test(LegislationPage::class, ['review' => $review])
        ->set('sourceUpload', UploadedFile::fake()->create('access-to-information-act.pdf', 256, 'application/pdf'))
        ->assertSee('Processing')
        ->assertSee('Queued')
        ->assertDontSee('Review record');

    $document = $review->fresh()->documents()->sole();

    runLegislationSourcePipeline($document->id);

    Queue::assertPushed(ProcessReviewLegislationSource::class, fn (ProcessReviewLegislationSource $job): bool => $job->documentId === $document->id && $job->step === 'extract');

    expect(data_get($document->fresh()->metadata, 'extraction.textract_job_id'))->toBe('job-123')
        ->and(data_get($document->fresh()->metadata, 'extraction.poll_attempts'))->toBe(0);

    app()->call([(new ProcessReviewLegislationSource($document->id)), 'handle']);

    Livewire::test(LegislationPage::class, ['review' => $review])
        ->call('refreshPendingAnalyses')
        ->assertSee('Filling record')
        ->assertDontSee('Needs review');

    Queue::assertPushed(ProcessReviewLegislationSource::class, fn (ProcessReviewLegislationSource $job): bool => $job->documentId === $document->id && $job->step === 'enrich');

    app()->call([(new ProcessReviewLegislationSource($document->id, 'enrich')), 'handle']);

    Livewire::test(LegislationPage::class, ['review' => $review])
        ->call('refreshPendingAnalyses')
        ->assertSee('Needs review');
});

test('processing legislation rows show the active progress stage in the records table', function () {
    $review = plsReview([
        'title' => 'Review of visible legislation progress stages',
    ]);

    Document::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Queued source',
        'document_type' => DocumentType::LegislationText,
        'metadata' => [
            'extraction' => [
                'status' => 'queued',
            ],
            'legislation_analysis' => [
                'status' => 'processing',
                'progress_stage' => 'queued',
                'title' => 'Queued source',
            ],
        ],
    ]);

    Document::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Extracting source',
        'document_type' => DocumentType::LegislationText,
        'metadata' => [
            'extraction' => [
                'status' => 'processing',
            ],
            'legislation_analysis' => [
                'status' => 'processing',
                'progress_stage' => 'extracting_text',
                'title' => 'Extracting source',
            ],
        ],
    ]);

    Document::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Filling source',
        'document_type' => DocumentType::LegislationText,
        'metadata' => [
            'extraction' => [
                'status' => 'processing',
            ],
            'legislation_analysis' => [
                'status' => 'processing',
                'progress_stage' => 'filling_record',
                'title' => 'Filling source',
            ],
        ],
    ]);

    Livewire::test(LegislationPage::class, ['review' => $review])
        ->assertSee('Queued')
        ->assertSee('Extracting text')
        ->assertSee('Filling record');
});

test('large legislation source prompts are trimmed before the ai extraction step', function () {
    Storage::fake('s3');
    Queue::fake();
    config()->set('pls_assistant.assistant_sources.extractor', 'textract');
    config()->set('pls_assistant.assistant_sources.source_disk', 's3');

    $review = plsReview([
        'title' => 'Review of large legislation prompt handling',
    ]);

    $largeText = implode("\n", array_fill(0, 1200, 'Section text describing the implementation, obligations, penalties, and procedural framework for the instrument.'));
    $largeText = "Southern Deep Port Development Facility Bill, 2024\nShort title: Deep Port Bill\nEnacted on June 1, 2024.\n\n".$largeText;

    $extractor = new class($largeText) implements AssistantSourceTextExtractor
    {
        public function __construct(private readonly string $content) {}

        public function extract(\App\Domain\Documents\AssistantSourceDocument|\App\Domain\Documents\Document $document): AssistantSourceExtractionResult
        {
            return AssistantSourceExtractionResult::completed(
                driver: 'stub',
                method: 'stubbed shared extractor',
                content: $this->content,
            );
        }
    };

    $factory = Mockery::mock(AssistantSourceTextExtractorFactory::class);
    $factory->shouldReceive('make')->once()->andReturn($extractor);
    app()->instance(AssistantSourceTextExtractorFactory::class, $factory);

    LegislationSourceExtractorAgent::fake(function (string $prompt) {
        expect(strlen($prompt))->toBeLessThan(22000)
            ->and($prompt)->toContain('Source text excerpt:')
            ->and($prompt)->toContain('Short title: Deep Port Bill')
            ->and($prompt)->not->toContain(str_repeat('Section text describing the implementation, obligations, penalties, and procedural framework for the instrument.', 20));

        return [
            'title' => 'Southern Deep Port Development Facility Bill, 2024',
            'short_title' => 'Deep Port Bill',
            'legislation_type' => LegislationType::Act->value,
            'date_enacted' => '2024-06-01',
            'summary' => 'Provides the framework for the southern deep port facility.',
            'relationship_type' => ReviewLegislationRelationshipType::Primary->value,
            'warnings' => [],
        ];
    });

    Livewire::test(LegislationPage::class, ['review' => $review])
        ->set('sourceUpload', UploadedFile::fake()->create('southern-deep-port-development-facility-bill-2024.pdf', 256, 'application/pdf'))
        ->assertSee('Processing');

    $document = $review->fresh()->documents()->sole();

    runLegislationSourcePipeline($document->id);

    Livewire::test(LegislationPage::class, ['review' => $review])
        ->call('refreshPendingAnalyses')
        ->assertSee('Needs review')
        ->assertSee('Southern Deep Port Development Facility Bill, 2024');
});

test('ai extraction failures do not fall back to heuristic legislation parsing', function () {
    Storage::fake('s3');
    Queue::fake();
    config()->set('pls_assistant.assistant_sources.extractor', 'textract');
    config()->set('pls_assistant.assistant_sources.source_disk', 's3');

    $review = plsReview([
        'title' => 'Review of failed ai extraction',
    ]);

    $extractor = new class implements AssistantSourceTextExtractor
    {
        public function extract(\App\Domain\Documents\AssistantSourceDocument|\App\Domain\Documents\Document $document): AssistantSourceExtractionResult
        {
            return AssistantSourceExtractionResult::completed(
                driver: 'stub',
                method: 'stubbed shared extractor',
                content: <<<'TEXT'
                BELIZE: SOUTHERN DEEP PORT DEVELOPMENT FACILITY BILL, 2024

                ARRANGEMENT OF CLAUSES
                1. Short title.
                2. Interpretation.

                A Bill for an Act to provide for the development and operation of a southern deep port facility and related matters.
                TEXT,
            );
        }
    };

    $factory = Mockery::mock(AssistantSourceTextExtractorFactory::class);
    $factory->shouldReceive('make')->once()->andReturn($extractor);
    app()->instance(AssistantSourceTextExtractorFactory::class, $factory);
    LegislationSourceExtractorAgent::fake(function () {
        throw new \RuntimeException('AI extraction failed');
    });

    $component = Livewire::test(LegislationPage::class, ['review' => $review])
        ->set('sourceUpload', UploadedFile::fake()->create('southern-deep-port-development-facility-bill-2024.pdf', 256, 'application/pdf'))
        ->assertSee('Processing')
        ->assertDontSee('Review record');

    $document = $review->fresh()->documents()->sole();

    runLegislationSourcePipeline($document->id);

    $component
        ->call('refreshPendingAnalyses')
        ->assertSee('Needs attention')
        ->assertDontSee('Needs review')
        ->assertDontSee('Review record');

    expect(data_get($document->fresh()->metadata, 'legislation_analysis.status'))->toBe('failed')
        ->and(data_get($document->fresh()->metadata, 'legislation_analysis.summary'))->toBe('')
        ->and(data_get($document->fresh()->metadata, 'legislation_analysis.title'))->toBe('Southern Deep Port Development Facility Bill 2024');
});

test('failed legislation analysis can be retried on the same source row', function () {
    Storage::fake('s3');
    Queue::fake();
    config()->set('pls_assistant.assistant_sources.extractor', 'textract');
    config()->set('pls_assistant.assistant_sources.source_disk', 's3');

    $review = plsReview([
        'title' => 'Review of failed legislation retries',
    ]);

    $document = Document::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Implementation Regulations',
        'document_type' => DocumentType::LegislationText,
        'storage_path' => 'pls/reviews/'.$review->id.'/documents/implementation-regulations.pdf',
        'mime_type' => 'application/pdf',
        'metadata' => [
            'disk' => 's3',
            'original_name' => 'implementation-regulations.pdf',
            'extraction' => [
                'status' => 'failed',
                'poll_attempts' => 4,
                'error' => 'The AI record step failed.',
            ],
            'legislation_analysis' => [
                'analysis_driver' => 'ai',
                'status' => 'failed',
                'source_document_id' => null,
                'source_label' => 'Implementation Regulations',
                'title' => 'Implementation Regulations',
                'warnings' => ['The source text was extracted, but the AI record step failed. Retry the source to try again.'],
            ],
        ],
    ]);

    Livewire::test(LegislationPage::class, ['review' => $review])
        ->assertSee('Needs attention')
        ->call('retrySourceAnalysis', $document->id)
        ->assertSee('Processing');

    Queue::assertPushed(ProcessReviewLegislationSource::class, fn (ProcessReviewLegislationSource $job): bool => $job->documentId === $document->id);

    expect(data_get($document->fresh()->metadata, 'extraction.status'))->toBe('queued')
        ->and(data_get($document->fresh()->metadata, 'extraction.poll_attempts'))->toBe(0)
        ->and(data_get($document->fresh()->metadata, 'legislation_analysis.status'))->toBe('processing');
});

test('review documents can be uploaded in batches analyzed and saved from the review workspace', function () {
    Storage::fake(config('filesystems.default'));
    Queue::fake();
    config()->set('pls_assistant.assistant_sources.extractor', 'local');
    config()->set('pls_assistant.assistant_sources.source_disk', config('filesystems.default'));

    $review = plsReview([
        'title' => 'Review of implementation reporting files',
    ]);

    $extractor = new class implements AssistantSourceTextExtractor
    {
        public function extract(\App\Domain\Documents\AssistantSourceDocument|\App\Domain\Documents\Document $document): AssistantSourceExtractionResult
        {
            return AssistantSourceExtractionResult::completed(
                driver: 'stub',
                method: 'stubbed shared extractor',
                content: <<<'TEXT'
                Implementation progress has stalled in three agencies.
                The document recommends revising reporting timetables and restoring funding certainty.
                Dated 15 January 2025.
                TEXT,
            );
        }
    };

    $factory = Mockery::mock(AssistantSourceTextExtractorFactory::class);
    $factory->shouldReceive('make')->twice()->andReturn($extractor);
    app()->instance(AssistantSourceTextExtractorFactory::class, $factory);
    ReviewDocumentExtractorAgent::fake([
        [
            'title' => 'Implementation Brief',
            'document_type' => DocumentType::ImplementationReport->value,
            'summary' => 'Summarizes stalled implementation across three agencies and recommends timetable changes.',
            'key_themes' => ['implementation delays', 'reporting timetable changes'],
            'notable_excerpts' => ['Implementation progress has stalled in three agencies.'],
            'important_dates' => ['2025-01-15'],
            'warnings' => [],
        ],
        [
            'title' => 'Consultation Digest',
            'document_type' => DocumentType::ConsultationSubmission->value,
            'summary' => 'Captures consultation concerns about reporting delays and weak implementation follow-through.',
            'key_themes' => ['consultation concerns', 'implementation follow-through'],
            'notable_excerpts' => ['The document recommends revising reporting timetables and restoring funding certainty.'],
            'important_dates' => ['2025-01-15'],
            'warnings' => [],
        ],
    ]);

    $component = Livewire::test(DocumentsPage::class, ['review' => $review])
        ->assertSeeHtml('wire:model.self="showEditDocumentModal"', false)
        ->set('documentUploads', [
            UploadedFile::fake()->create('implementation-brief.pdf', 256, 'application/pdf'),
            UploadedFile::fake()->create('consultation-digest.txt', 16, 'text/plain'),
        ])
        ->assertHasNoErrors()
        ->assertSee('Queued');

    $queuedDocuments = $review->fresh()->documents()
        ->where('document_type', '!=', DocumentType::LegislationText->value)
        ->get();

    expect($queuedDocuments)->toHaveCount(2);

    Queue::assertPushed(ProcessReviewDocument::class, 2);

    foreach ($queuedDocuments as $queuedDocument) {
        runReviewDocumentPipeline($queuedDocument->id);
    }

    $implementationBrief = $review->fresh()->documents()
        ->where('title', 'Implementation Brief')
        ->firstOrFail();

    $consultationDigest = $review->fresh()->documents()
        ->where('title', 'Consultation Digest')
        ->firstOrFail();

    $component
        ->call('refreshPendingAnalyses')
        ->assertSee('Implementation Brief')
        ->assertSee('Consultation Digest')
        ->assertSee('Saved');

    expect(data_get($implementationBrief->metadata, 'document_analysis.status'))->toBe('saved')
        ->and(data_get($implementationBrief->metadata, 'document_analysis.key_themes'))->toBe(['implementation delays', 'reporting timetable changes'])
        ->and($implementationBrief->document_type)->toBe(DocumentType::ImplementationReport);

    expect(data_get($consultationDigest->metadata, 'document_analysis.status'))->toBe('saved')
        ->and($consultationDigest->document_type)->toBe(DocumentType::ConsultationSubmission);

    Storage::disk(config('filesystems.default'))->assertExists($implementationBrief->storage_path);
    Storage::disk(config('filesystems.default'))->assertExists($consultationDigest->storage_path);

    $component
        ->call('startEditingDocument', $implementationBrief->id)
        ->assertSet('showEditDocumentModal', true)
        ->set('documentTitle', 'Implementation Brief Revised')
        ->set('documentSummary', 'Updated summary of implementation delays and timetable reform proposals.')
        ->call('saveDocumentEdits')
        ->assertSet('showEditDocumentModal', false)
        ->assertHasNoErrors();

    $updatedDocument = $implementationBrief->fresh();

    expect($updatedDocument->title)->toBe('Implementation Brief Revised')
        ->and($updatedDocument->summary)->toBe('Updated summary of implementation delays and timetable reform proposals.')
        ->and(data_get($updatedDocument->metadata, 'document_analysis.key_themes'))->toBe(['implementation delays', 'reporting timetable changes']);

    $component
        ->call('confirmDeletion', $updatedDocument->id)
        ->assertHasNoErrors()
        ->assertDontSee('Implementation Brief Revised');

    $this->assertDatabaseMissing('documents', [
        'id' => $updatedDocument->id,
    ]);

    Storage::disk(config('filesystems.default'))->assertMissing($updatedDocument->storage_path);
});

test('failed document analysis can be retried on the same review document row', function () {
    Storage::fake(config('filesystems.default'));
    Queue::fake();
    config()->set('pls_assistant.assistant_sources.extractor', 'local');
    config()->set('pls_assistant.assistant_sources.source_disk', config('filesystems.default'));

    $review = plsReview([
        'title' => 'Review of implementation reporting failures',
    ]);

    $extractor = new class implements AssistantSourceTextExtractor
    {
        public function extract(\App\Domain\Documents\AssistantSourceDocument|\App\Domain\Documents\Document $document): AssistantSourceExtractionResult
        {
            return AssistantSourceExtractionResult::completed(
                driver: 'stub',
                method: 'stubbed shared extractor',
                content: <<<'TEXT'
                The implementation report records missed statutory deadlines.
                Published on 2 February 2025.
                TEXT,
            );
        }
    };

    $factory = Mockery::mock(AssistantSourceTextExtractorFactory::class);
    $factory->shouldReceive('make')->once()->andReturn($extractor);
    app()->instance(AssistantSourceTextExtractorFactory::class, $factory);
    ReviewDocumentExtractorAgent::fake(function () {
        throw new \RuntimeException('AI extraction failed');
    });

    $component = Livewire::test(DocumentsPage::class, ['review' => $review])
        ->set('documentUploads', [
            UploadedFile::fake()->create('implementation-report.pdf', 256, 'application/pdf'),
        ])
        ->assertSee('Queued');

    $document = $review->fresh()->documents()->firstOrFail();

    Queue::assertPushed(ProcessReviewDocument::class, fn (ProcessReviewDocument $job): bool => $job->documentId === $document->id && $job->step === 'extract');

    runReviewDocumentPipeline($document->id);

    $component
        ->call('refreshPendingAnalyses')
        ->assertSee('Needs attention');

    expect(data_get($document->fresh()->metadata, 'document_analysis.status'))->toBe('needs_attention');

    ReviewDocumentExtractorAgent::fake([[
        'title' => 'Implementation Report',
        'document_type' => DocumentType::ImplementationReport->value,
        'summary' => 'Records missed statutory deadlines and implementation slippage.',
        'key_themes' => ['missed deadlines'],
        'notable_excerpts' => ['The implementation report records missed statutory deadlines.'],
        'important_dates' => ['2025-02-02'],
        'warnings' => [],
    ]]);

    $component
        ->call('retryDocumentAnalysis', $document->id)
        ->assertHasNoErrors()
        ->assertSee('Queued');

    Queue::assertPushed(ProcessReviewDocument::class, fn (ProcessReviewDocument $job): bool => $job->documentId === $document->id);

    runReviewDocumentPipeline($document->id);

    $component
        ->call('refreshPendingAnalyses')
        ->assertSee('Implementation Report')
        ->assertSee('Saved');

    expect($document->fresh()->id)->toBe($document->id)
        ->and(data_get($document->fresh()->metadata, 'document_analysis.status'))->toBe('saved')
        ->and($document->fresh()->title)->toBe('Implementation Report');
});

test('processing document extraction requeues in the background until text is ready', function () {
    Storage::fake(config('filesystems.default'));
    Queue::fake();
    config()->set('pls_assistant.assistant_sources.extractor', 'local');
    config()->set('pls_assistant.assistant_sources.source_disk', config('filesystems.default'));

    $review = plsReview([
        'title' => 'Review of document processing state',
    ]);

    $extractor = new class implements AssistantSourceTextExtractor
    {
        public function extract(\App\Domain\Documents\AssistantSourceDocument|\App\Domain\Documents\Document $document): AssistantSourceExtractionResult
        {
            if (data_get($document->metadata, 'extraction.textract_job_id') === null) {
                return AssistantSourceExtractionResult::processing(
                    driver: 'stub',
                    method: 'stubbed shared extractor',
                    metadata: [
                        'textract_job_id' => 'document-job-123',
                    ],
                    pollAfterSeconds: 3,
                );
            }

            return AssistantSourceExtractionResult::completed(
                driver: 'stub',
                method: 'stubbed shared extractor',
                content: <<<'TEXT'
                Implementation progress has stalled in three agencies.
                The document recommends revising reporting timetables and restoring funding certainty.
                Dated 15 January 2025.
                TEXT,
            );
        }
    };

    $factory = Mockery::mock(AssistantSourceTextExtractorFactory::class);
    $factory->shouldReceive('make')->times(2)->andReturn($extractor);
    app()->instance(AssistantSourceTextExtractorFactory::class, $factory);
    ReviewDocumentExtractorAgent::fake([[
        'title' => 'Implementation Brief',
        'document_type' => DocumentType::ImplementationReport->value,
        'summary' => 'Summarizes stalled implementation across three agencies and recommends timetable changes.',
        'key_themes' => ['implementation delays'],
        'notable_excerpts' => ['Implementation progress has stalled in three agencies.'],
        'important_dates' => ['2025-01-15'],
        'warnings' => [],
    ]]);

    Livewire::test(DocumentsPage::class, ['review' => $review])
        ->set('documentUploads', [
            UploadedFile::fake()->create('implementation-brief.pdf', 256, 'application/pdf'),
        ])
        ->assertSee('Queued');

    $document = $review->fresh()->documents()->firstOrFail();

    app()->call([(new ProcessReviewDocument($document->id)), 'handle']);

    Queue::assertPushed(ProcessReviewDocument::class, fn (ProcessReviewDocument $job): bool => $job->documentId === $document->id && $job->step === 'extract');

    expect(data_get($document->fresh()->metadata, 'extraction.textract_job_id'))->toBe('document-job-123')
        ->and(data_get($document->fresh()->metadata, 'extraction.poll_attempts'))->toBe(0);

    app()->call([(new ProcessReviewDocument($document->id)), 'handle']);

    Livewire::test(DocumentsPage::class, ['review' => $review])
        ->call('refreshPendingAnalyses')
        ->assertSee('Filling record');

    Queue::assertPushed(ProcessReviewDocument::class, fn (ProcessReviewDocument $job): bool => $job->documentId === $document->id && $job->step === 'enrich');

    app()->call([(new ProcessReviewDocument($document->id, 'enrich')), 'handle']);

    Livewire::test(DocumentsPage::class, ['review' => $review])
        ->call('refreshPendingAnalyses')
        ->assertSee('Saved')
        ->assertSee('Implementation Brief');
});

test('processing document rows show the active progress stage in the records table', function () {
    $review = plsReview([
        'title' => 'Review of visible document progress stages',
    ]);

    Document::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Queued document',
        'document_type' => DocumentType::GroupReport,
        'metadata' => [
            'extraction' => [
                'status' => 'queued',
            ],
            'document_analysis' => [
                'status' => 'processing',
                'progress_stage' => 'queued',
                'title' => 'Queued document',
            ],
        ],
    ]);

    Document::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Extracting document',
        'document_type' => DocumentType::GroupReport,
        'metadata' => [
            'extraction' => [
                'status' => 'processing',
            ],
            'document_analysis' => [
                'status' => 'processing',
                'progress_stage' => 'extracting_text',
                'title' => 'Extracting document',
            ],
        ],
    ]);

    Document::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Filling document',
        'document_type' => DocumentType::GroupReport,
        'metadata' => [
            'extraction' => [
                'status' => 'processing',
            ],
            'document_analysis' => [
                'status' => 'processing',
                'progress_stage' => 'filling_record',
                'title' => 'Filling document',
            ],
        ],
    ]);

    Livewire::test(DocumentsPage::class, ['review' => $review])
        ->assertSee('Queued')
        ->assertSee('Extracting text')
        ->assertSee('Filling record');
});

test('legislation source documents do not appear in the documents workspace', function () {
    $review = plsReview([
        'title' => 'Review of separated workspaces',
    ]);

    Document::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Legislation upload',
        'document_type' => DocumentType::LegislationText,
    ]);

    Document::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Implementation memo',
        'document_type' => DocumentType::ImplementationReport,
    ]);

    Livewire::test(DocumentsPage::class, ['review' => $review])
        ->assertSee('Implementation memo')
        ->assertDontSee('Legislation upload');
});

test('workflow document metrics exclude legislation source uploads', function () {
    $review = plsReview([
        'title' => 'Workflow document counts',
    ]);

    Document::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Legislation upload',
        'document_type' => DocumentType::LegislationText,
    ]);

    Document::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Working note',
        'document_type' => DocumentType::GroupReport,
    ]);

    $loadedReview = $review->fresh()->load([
        'steps',
        'legislation',
        'legislationObjectives',
        'documents',
        'evidenceItems',
        'implementingAgencies',
        'stakeholders',
        'consultations',
        'submissions',
        'findings',
        'recommendations',
        'reports',
        'governmentResponses',
    ]);

    $cards = Livewire::test(WorkflowPage::class, ['review' => $review])
        ->instance()
        ->stepMetricCards($loadedReview, $loadedReview->currentStep());

    expect(collect($cards)->firstWhere('label', 'Working documents')['value'])->toBe('1');
});

test('uploaded legislation can infer delegated relationship details', function () {
    Storage::fake('s3');
    Queue::fake();
    config()->set('pls_assistant.assistant_sources.extractor', 'textract');
    config()->set('pls_assistant.assistant_sources.source_disk', 's3');

    $review = plsReview([
        'title' => 'Review of delegated powers reporting',
    ]);

    $extractor = new class implements AssistantSourceTextExtractor
    {
        public function extract(\App\Domain\Documents\AssistantSourceDocument|\App\Domain\Documents\Document $document): AssistantSourceExtractionResult
        {
            return AssistantSourceExtractionResult::completed(
                driver: 'stub',
                method: 'stubbed shared extractor',
                content: <<<'TEXT'
                Implementation Regulations

                These regulations set reporting deadlines for implementing agencies and identify the responsible minister.

                Made on 12 March 2014.
                TEXT,
            );
        }
    };

    $factory = Mockery::mock(AssistantSourceTextExtractorFactory::class);
    $factory->shouldReceive('make')->once()->andReturn($extractor);
    app()->instance(AssistantSourceTextExtractorFactory::class, $factory);
    LegislationSourceExtractorAgent::fake([[
        'title' => 'Implementation Regulations',
        'short_title' => null,
        'legislation_type' => LegislationType::Regulation->value,
        'date_enacted' => '2014-03-12',
        'summary' => 'Sets reporting deadlines for implementing agencies and identifies the responsible minister.',
        'relationship_type' => ReviewLegislationRelationshipType::Delegated->value,
        'warnings' => [],
    ]]);

    $component = Livewire::test(LegislationPage::class, ['review' => $review])
        ->set('sourceUpload', UploadedFile::fake()->create('implementation-regulations.pdf', 256, 'application/pdf'))
        ->assertSee('Processing');

    $document = $review->fresh()->documents()->sole();

    runLegislationSourcePipeline($document->id);

    $component
        ->call('refreshPendingAnalyses')
        ->assertSee('Implementation Regulations')
        ->call('startReviewDocument', $document->id)
        ->assertSet('analysisTitle', 'Implementation Regulations')
        ->assertSet('analysisType', LegislationType::Regulation->value)
        ->assertSet('analysisDateEnacted', '2014-03-12')
        ->assertSet('analysisRelationshipType', ReviewLegislationRelationshipType::Delegated->value)
        ->assertSee('Review record');
});

test('best effort legislation parsing still enters the review state with warnings', function () {
    Storage::fake('s3');
    Queue::fake();
    config()->set('pls_assistant.assistant_sources.extractor', 'textract');
    config()->set('pls_assistant.assistant_sources.source_disk', 's3');

    $review = plsReview([
        'title' => 'Review of incomplete source text',
    ]);

    $extractor = new class implements AssistantSourceTextExtractor
    {
        public function extract(\App\Domain\Documents\AssistantSourceDocument|\App\Domain\Documents\Document $document): AssistantSourceExtractionResult
        {
            return AssistantSourceExtractionResult::completed(
                driver: 'stub',
                method: 'stubbed shared extractor',
                content: 'Implementation note with no clear enactment details or formal heading.',
            );
        }
    };

    $factory = Mockery::mock(AssistantSourceTextExtractorFactory::class);
    $factory->shouldReceive('make')->once()->andReturn($extractor);
    app()->instance(AssistantSourceTextExtractorFactory::class, $factory);
    LegislationSourceExtractorAgent::fake([[
        'title' => 'Implementation Note',
        'short_title' => null,
        'legislation_type' => LegislationType::Act->value,
        'date_enacted' => null,
        'summary' => null,
        'relationship_type' => ReviewLegislationRelationshipType::Primary->value,
        'warnings' => ['The summary needs manual review because the extracted source text was limited.'],
    ]]);

    $component = Livewire::test(LegislationPage::class, ['review' => $review])
        ->set('sourceUpload', UploadedFile::fake()->create('working-note.pdf', 128, 'application/pdf'))
        ->assertSee('Processing');

    $document = $review->fresh()->documents()->sole();

    runLegislationSourcePipeline($document->id);

    $component
        ->call('refreshPendingAnalyses')
        ->assertSee('Implementation Note')
        ->call('startReviewDocument', $document->id)
        ->assertSet('analysisTitle', 'Implementation Note')
        ->assertSee('Needs review')
        ->assertSee('Review record');
});

test('stale legislation rows are surfaced for retry instead of refreshing inline', function () {
    Storage::fake('s3');
    config()->set('pls_assistant.assistant_sources.extractor', 'textract');
    config()->set('pls_assistant.assistant_sources.source_disk', 's3');

    $review = plsReview([
        'title' => 'Review of stale legislation analysis',
    ]);

    $document = Document::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Southern Deep Port Development Facility Bill 2024',
        'document_type' => DocumentType::LegislationText,
        'storage_path' => 'pls/reviews/'.$review->id.'/documents/southern-deep-port.pdf',
        'mime_type' => 'application/pdf',
        'metadata' => [
            'disk' => 's3',
            'original_name' => 'Southern_Deep_Port_Development_Facility_Bill_2024_d5aa96a3ea.pdf',
            'legislation_analysis' => [
                'status' => 'needs_review',
                'source_document_id' => null,
                'source_label' => 'Southern Deep Port Development Facility Bill 2024',
                'title' => 'Southern Deep Port Development Facility Bill, 2024',
                'short_title' => 'Southern Deep Port Development Facility Bill',
                'legislation_type' => 'act',
                'date_enacted' => '',
                'summary' => '',
                'relationship_type' => 'primary',
                'warnings' => [
                    'Confirm or correct the fields before saving.',
                    'This looks like a bill or draft text, so an enactment date may not be available yet.',
                    'The summary needs manual review because the extracted source text was limited.',
                ],
                'duplicate_candidates' => [],
            ],
        ],
    ]);

    Storage::disk('s3')->put($document->storage_path, 'fake pdf bytes');

    Livewire::test(LegislationPage::class, ['review' => $review])
        ->assertSee('Needs attention')
        ->call('startReviewDocument', $document->id)
        ->assertDontSee('Review record');
});

test('duplicate legislation matches can be updated from the inline review state', function () {
    Storage::fake('s3');
    Queue::fake();
    config()->set('pls_assistant.assistant_sources.extractor', 'textract');
    config()->set('pls_assistant.assistant_sources.source_disk', 's3');

    $review = plsReview([
        'title' => 'Review of an existing public records statute',
    ]);

    $existingLegislation = Legislation::factory()->create([
        'jurisdiction_id' => $review->jurisdiction_id,
        'title' => 'Public Records Act',
        'short_title' => 'PRA',
        'legislation_type' => LegislationType::Act,
        'summary' => 'Older summary.',
    ]);

    $extractor = new class implements AssistantSourceTextExtractor
    {
        public function extract(\App\Domain\Documents\AssistantSourceDocument|\App\Domain\Documents\Document $document): AssistantSourceExtractionResult
        {
            return AssistantSourceExtractionResult::completed(
                driver: 'stub',
                method: 'stubbed shared extractor',
                content: <<<'TEXT'
                Public Records Act
                Short title: PRA

                This Act refreshes public access rules, names the oversight commission, and allows regulations for implementation.

                Enacted on June 1, 2018.
                TEXT,
            );
        }
    };

    $factory = Mockery::mock(AssistantSourceTextExtractorFactory::class);
    $factory->shouldReceive('make')->once()->andReturn($extractor);
    app()->instance(AssistantSourceTextExtractorFactory::class, $factory);
    LegislationSourceExtractorAgent::fake([[
        'title' => 'Public Records Act',
        'short_title' => 'PRA',
        'legislation_type' => LegislationType::Act->value,
        'date_enacted' => '2018-06-01',
        'summary' => 'Refreshes public access rules, names the oversight commission, and allows regulations for implementation.',
        'relationship_type' => ReviewLegislationRelationshipType::Primary->value,
        'warnings' => [],
    ]]);

    $component = Livewire::test(LegislationPage::class, ['review' => $review])
        ->set('sourceUpload', UploadedFile::fake()->create('public-records-act.pdf', 256, 'application/pdf'))
        ->assertSee('Processing');

    $document = $review->fresh()->documents()->sole();

    runLegislationSourcePipeline($document->id);

    $component
        ->call('refreshPendingAnalyses')
        ->assertSee('Public Records Act')
        ->call('startReviewDocument', $document->id)
        ->assertSet('analysisSaveMode', 'update')
        ->assertSee('Possible match found')
        ->assertSee('Update existing record')
        ->set('analysisExistingLegislationId', (string) $existingLegislation->id)
        ->call('saveAnalyzedLegislation')
        ->assertHasNoErrors()
        ->assertSee('Public Records Act');

    $updatedLegislation = $existingLegislation->fresh();

    expect(str_contains(strtolower((string) $updatedLegislation->summary), 'refreshes public access rules'))->toBeTrue();
    expect($updatedLegislation->source_document_id)->toBe($review->fresh()->documents()->sole()->id);

    $this->assertDatabaseHas('pls_review_legislation', [
        'pls_review_id' => $review->id,
        'legislation_id' => $existingLegislation->id,
        'relationship_type' => ReviewLegislationRelationshipType::Primary->value,
    ]);
});

test('findings and recommendations can be added from the review workspace', function () {
    $review = plsReview([
        'title' => 'Review of implementation bottlenecks',
    ]);

    $component = Livewire::test(AnalysisPage::class, ['review' => $review])
        ->assertSeeHtml('wire:model.self="showAddFindingModal"', false)
        ->assertSeeHtml('wire:model.self="showEditFindingModal"', false)
        ->assertSeeHtml('wire:model.self="showEditRecommendationModal"', false)
        ->call('prepareFindingCreate')
        ->assertSet('showAddFindingModal', true)
        ->set('findingTitle', 'Agency reporting remains inconsistent')
        ->set('findingSummary', 'Quarterly implementation reports are incomplete across multiple agencies.')
        ->call('storeFinding')
        ->assertSet('showAddFindingModal', false)
        ->assertHasNoErrors()
        ->assertSee('Agency reporting remains inconsistent');

    $findingId = (string) $review->fresh()->findings()->where('title', 'Agency reporting remains inconsistent')->value('id');

    $component
        ->call('prepareRecommendationCreate', (int) $findingId)
        ->assertJs("window.Flux.modal('add-analysis-recommendation').show()")
        ->assertSet('recommendationFindingId', $findingId)
        ->set('recommendationTitle', 'Mandate a standard reporting template')
        ->set('recommendationDescription', 'Require a common quarterly template and review-group compliance review.')
        ->call('storeRecommendation')
        ->assertJs("window.Flux.modal('add-analysis-recommendation').close()")
        ->assertHasNoErrors()
        ->assertSee('Mandate a standard reporting template');

    $this->assertDatabaseHas('recommendations', [
        'pls_review_id' => $review->id,
        'finding_id' => (int) $findingId,
        'title' => 'Mandate a standard reporting template',
    ]);
});

test('findings and recommendations can be edited and deleted from the review workspace', function () {
    $review = plsReview([
        'title' => 'Review of implementation bottlenecks',
    ]);

    $finding = $review->findings()->create([
        'title' => 'Agency reporting remains inconsistent',
        'finding_type' => \App\Domain\Analysis\Enums\FindingType::ImplementationGap,
        'summary' => 'Quarterly implementation reports are incomplete across multiple agencies.',
        'detail' => null,
    ]);

    $recommendation = $review->recommendations()->create([
        'finding_id' => $finding->id,
        'title' => 'Mandate a standard reporting template',
        'description' => 'Require a common quarterly template and review-group compliance review.',
        'recommendation_type' => \App\Domain\Analysis\Enums\RecommendationType::ImproveImplementation,
    ]);

    $component = Livewire::test(AnalysisPage::class, ['review' => $review])
        ->call('startEditingFinding', $finding->id)
        ->assertSet('showEditFindingModal', true)
        ->set('findingTitle', 'Agency reporting standards remain inconsistent')
        ->set('findingSummary', 'Reporting formats still vary significantly across implementing agencies.')
        ->call('updateFinding')
        ->assertSet('showEditFindingModal', false)
        ->assertHasNoErrors()
        ->assertSee('Agency reporting standards remain inconsistent');

    $component
        ->call('startEditingRecommendation', $recommendation->id)
        ->assertSet('showEditRecommendationModal', true)
        ->set('recommendationTitle', 'Adopt a common quarterly reporting template')
        ->set('recommendationDescription', 'Issue a single template and require review-group compliance tracking.')
        ->call('updateRecommendation')
        ->assertSet('showEditRecommendationModal', false)
        ->assertHasNoErrors()
        ->assertSee('Adopt a common quarterly reporting template');

    $component
        ->call('confirmDeletion', 'recommendation', $recommendation->id)
        ->assertHasNoErrors()
        ->assertDontSee('Adopt a common quarterly reporting template');

    $component
        ->call('confirmDeletion', 'finding', $finding->id)
        ->assertHasNoErrors()
        ->assertDontSee('Agency reporting standards remain inconsistent');

    $this->assertDatabaseMissing('recommendations', [
        'id' => $recommendation->id,
    ]);

    $this->assertDatabaseMissing('findings', [
        'id' => $finding->id,
    ]);
});

test('analysis workspace renders findings in a compact table layout', function () {
    $review = plsReview([
        'title' => 'Review of compact analysis layout',
    ]);

    $finding = $review->findings()->create([
        'title' => 'Delegated regulations were not updated in line with reporting reforms',
        'finding_type' => \App\Domain\Analysis\Enums\FindingType::ImplementationGap,
        'summary' => 'This finding summary is intentionally long so the workspace shows a compact preview instead of rendering a large narrative block for every analytical record in the list, while still letting the reviewer expand it in place to read the complete wording when they need the full context.',
        'detail' => null,
    ]);

    $review->recommendations()->create([
        'finding_id' => $finding->id,
        'title' => 'Update delegated regulations',
        'description' => 'Align delegated regulations with the reporting reforms, publish a short implementation note for stakeholders, issue updated guidance for agencies, and confirm the revised reporting obligations in the next quarterly compliance cycle.',
        'recommendation_type' => \App\Domain\Analysis\Enums\RecommendationType::ImproveImplementation,
    ]);

    Livewire::test(AnalysisPage::class, ['review' => $review])
        ->assertSee('Add finding')
        ->assertSee('Add recommendation')
        ->assertSee('Show more')
        ->assertSee('Recommendations')
        ->assertSee('Update delegated regulations')
        ->assertDontSee('Record')
        ->assertDontSee('Gov. responses')
        ->assertDontSee('No findings or recommendations recorded yet.');
});

test('reports can be created from the review workspace and linked to a review document', function () {
    $review = plsReview([
        'title' => 'Review of publication and dissemination obligations',
    ]);

    $document = Document::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Draft dissemination report',
        'document_type' => DocumentType::DraftReport,
    ]);

    Livewire::test(ReportsPage::class, ['review' => $review])
        ->assertSeeHtml('wire:model.self="showAddReportModal"', false)
        ->assertSeeHtml('wire:model.self="showEditReportModal"', false)
        ->assertSeeHtml('wire:model.self="showAddGovernmentResponseModal"', false)
        ->call('prepareReportCreate', ReportType::DraftReport->value, ReportStatus::Published->value)
        ->assertSet('showAddReportModal', true)
        ->set('reportTitle', 'Draft PLS Report on Dissemination Obligations')
        ->set('reportType', ReportType::DraftReport->value)
        ->set('reportStatus', ReportStatus::Published->value)
        ->set('reportDocumentId', (string) $document->id)
        ->set('reportPublishedAt', '2026-03-10')
        ->call('storeReport')
        ->assertSet('showAddReportModal', false)
        ->assertHasNoErrors()
        ->assertSee('Draft PLS Report on Dissemination Obligations')
        ->assertSee('Draft dissemination report');

    $this->assertDatabaseHas('reports', [
        'pls_review_id' => $review->id,
        'title' => 'Draft PLS Report on Dissemination Obligations',
        'report_type' => ReportType::DraftReport->value,
        'status' => ReportStatus::Published->value,
        'document_id' => $document->id,
    ]);
});

test('reports can be edited and deleted from the review workspace', function () {
    $review = plsReview([
        'title' => 'Review of publication and dissemination obligations',
    ]);

    $document = Document::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Published dissemination packet',
        'document_type' => DocumentType::FinalReport,
    ]);

    /** @var Report $report */
    $report = $review->reports()->create([
        'title' => 'Draft dissemination report',
        'report_type' => ReportType::DraftReport,
        'status' => ReportStatus::Draft,
        'document_id' => null,
        'published_at' => null,
    ]);

    $component = Livewire::test(ReportsPage::class, ['review' => $review])
        ->call('startEditingReport', $report->id)
        ->assertSet('showEditReportModal', true)
        ->set('reportTitle', 'Final dissemination report record')
        ->set('reportType', ReportType::FinalReport->value)
        ->set('reportStatus', ReportStatus::Published->value)
        ->set('reportDocumentId', (string) $document->id)
        ->set('reportPublishedAt', '2026-03-11')
        ->call('updateReport')
        ->assertSet('showEditReportModal', false)
        ->assertHasNoErrors()
        ->assertSee('Final dissemination report record');

    $updatedReport = $report->fresh();

    expect($updatedReport->title)->toBe('Final dissemination report record')
        ->and($updatedReport->report_type)->toBe(ReportType::FinalReport)
        ->and($updatedReport->status)->toBe(ReportStatus::Published)
        ->and($updatedReport->document_id)->toBe($document->id);

    $component
        ->call('confirmDeletion', 'report', $report->id)
        ->assertHasNoErrors()
        ->assertDontSee('Final dissemination report record');

    $this->assertDatabaseMissing('reports', [
        'id' => $report->id,
    ]);
});

test('reporting quick actions prefill report and government response forms', function () {
    Carbon::setTestNow('2026-03-18 10:00:00');

    try {
        $review = plsReview([
            'title' => 'Review of publication and executive follow-up',
        ]);

        $awaitingResponseReport = $review->reports()->create([
            'title' => 'Final Report on Publication Duties',
            'report_type' => ReportType::FinalReport,
            'status' => ReportStatus::Published,
            'document_id' => null,
            'published_at' => now()->subDay(),
        ]);

        Livewire::test(ReportsPage::class, ['review' => $review])
            ->call('prepareReportCreate', ReportType::FinalReport->value, ReportStatus::Published->value)
            ->assertSet('showAddReportModal', true)
            ->assertSet('reportType', ReportType::FinalReport->value)
            ->assertSet('reportStatus', ReportStatus::Published->value)
            ->assertSet('reportPublishedAt', '2026-03-18')
            ->call('prepareGovernmentResponseCreate', null, GovernmentResponseStatus::Received->value)
            ->assertSet('showAddGovernmentResponseModal', true)
            ->assertSet('governmentResponseReportId', (string) $awaitingResponseReport->id)
            ->assertSet('governmentResponseStatus', GovernmentResponseStatus::Received->value)
            ->assertSet('governmentResponseReceivedAt', '2026-03-18');
    } finally {
        Carbon::setTestNow();
    }
});

test('government responses can be recorded from the review workspace', function () {
    $review = plsReview([
        'title' => 'Review of executive follow-up on group reports',
    ]);

    $document = Document::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Executive response memorandum',
        'document_type' => DocumentType::GovernmentResponse,
    ]);

    $report = $review->reports()->create([
        'title' => 'Final PLS Report on Executive Follow-Up',
        'report_type' => ReportType::FinalReport,
        'status' => ReportStatus::Published,
        'document_id' => null,
        'published_at' => now()->subDays(10),
    ]);

    Livewire::test(ReportsPage::class, ['review' => $review])
        ->call('prepareGovernmentResponseCreate', $report->id)
        ->assertSet('showAddGovernmentResponseModal', true)
        ->set('governmentResponseReportId', (string) $report->id)
        ->set('governmentResponseDocumentId', (string) $document->id)
        ->set('governmentResponseStatus', GovernmentResponseStatus::Received->value)
        ->set('governmentResponseReceivedAt', '2026-03-11')
        ->set('governmentResponseSummary', 'Cabinet accepted the primary recommendation and requested a six-month implementation update.')
        ->call('storeGovernmentResponse')
        ->assertSet('showAddGovernmentResponseModal', false)
        ->assertHasNoErrors()
        ->assertDispatched('review-workspace-updated', toast: Toast::success(
            __('Response recorded'),
            __('Government response recorded for this review.'),
        ))
        ->assertSee('Response received')
        ->assertSee('Executive response memorandum');

    $this->assertDatabaseHas('government_responses', [
        'pls_review_id' => $review->id,
        'report_id' => $report->id,
        'document_id' => $document->id,
        'response_status' => GovernmentResponseStatus::Received->value,
        'summary' => 'Cabinet accepted the primary recommendation and requested a six-month implementation update.',
    ]);
});

test('reporting workspace surfaces analysis inputs and awaiting response work', function () {
    $review = plsReview([
        'title' => 'Review of implementation reporting obligations',
    ]);

    $finding = $review->findings()->create([
        'title' => 'Agency reporting remains inconsistent',
        'finding_type' => FindingType::ImplementationGap,
        'summary' => 'Quarterly implementation reports are incomplete across multiple agencies.',
        'detail' => null,
    ]);

    $review->recommendations()->create([
        'finding_id' => $finding->id,
        'title' => 'Mandate a standard reporting template',
        'description' => 'Require a common quarterly template and review-group compliance review.',
        'recommendation_type' => RecommendationType::ImproveImplementation,
    ]);

    $review->reports()->create([
        'title' => 'Final Report on Implementation Reporting',
        'report_type' => ReportType::FinalReport,
        'status' => ReportStatus::Published,
        'document_id' => null,
        'published_at' => now()->subWeek(),
    ]);

    Livewire::test(ReportsPage::class, ['review' => $review])
        ->assertSee('Reports')
        ->assertSee('1 findings and 1 recommendations available')
        ->assertSee('Awaiting response on published final reports')
        ->assertSee('Final Report on Implementation Reporting');
});

test('published final reports without responses are shown as awaiting response', function () {
    $review = plsReview([
        'title' => 'Review of publication duties under the access statute',
    ]);

    $review->forceFill([
        'current_step_number' => 9,
    ])->save();

    $review->reports()->create([
        'title' => 'Final Report on Publication Duties',
        'report_type' => ReportType::FinalReport,
        'status' => ReportStatus::Published,
        'document_id' => null,
        'published_at' => now()->subWeek(),
    ]);

    Livewire::test(ReportsPage::class, ['review' => $review])
        ->assertSee('Track the government response')
        ->assertSee('Awaiting response')
        ->assertSee('Final Report on Publication Duties');
});

test('workspace guidance reflects the current workflow step', function () {
    $review = plsReview([
        'title' => 'Review of publication duties under the access statute',
    ]);

    $review->forceFill([
        'current_step_number' => 9,
    ])->save();

    Livewire::test(WorkflowPage::class, ['review' => $review->fresh()])
        ->assertSee('Invite a response from the government to "comply or explain"')
        ->assertSee('Current')
        ->assertSee('Monitor whether the executive has responded and whether commitments are on record.');
});
