<?php

use App\Ai\Agents\LegislationSourceExtractorAgent;
use App\Domain\Analysis\Finding;
use App\Domain\Documents\Actions\AnalyzeReviewDocument;
use App\Domain\Documents\Actions\ChunkDocumentText;
use App\Domain\Documents\Actions\PersistReviewDocumentState;
use App\Domain\Documents\Actions\RecordDocumentExtraction;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Legislation\Actions\AnalyzeLegislationSource;
use App\Domain\Legislation\Actions\PersistLegislationSourceState;
use App\Domain\Legislation\Enums\LegislationType;
use App\Domain\Legislation\Legislation;
use App\Domain\Reviews\Enums\PlsReviewMembershipRole;
use App\Jobs\ProcessReviewDocument;
use App\Jobs\ProcessReviewLegislationSource;
use App\Livewire\Pls\Reviews\SourcePage;
use App\Models\User;
use App\Support\PlsAssistant\LegislationSourceIdentity;
use App\Support\PlsAssistant\ReviewAssistantContextBuilder;
use App\Support\PlsAssistant\TextractPdfExtractor;
use Aws\Result;
use Aws\Textract\TextractClient;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->reviewer()->create());
});

test('source pages show escaped ordered text and honest coverage to review members', function () {
    $review = plsReview();
    $document = Document::factory()->create(['pls_review_id' => $review->id]);
    $document->chunks()->create(['chunk_index' => 1, 'content' => 'Closing schedule', 'token_count' => 5]);
    $document->chunks()->create(['chunk_index' => 0, 'content' => '<script>example()</script>', 'token_count' => 5]);
    $this->get(route('pls.reviews.documents.show', [$review, $document]))->assertOk()
        ->assertSeeInOrder(['<script>example()</script>', 'Closing schedule'])
        ->assertDontSee('<script>example()</script>', false)->assertSee('Page coverage unavailable');
    $this->actingAs(User::factory()->reviewer()->create());
    $this->get(route('pls.reviews.documents.show', [$review, $document]))->assertForbidden();
});

test('source readers reject another reviews document', function () {
    $review = plsReview();
    $document = Document::factory()->create();
    $this->get(route('pls.reviews.documents.show', [$review, $document]))->assertNotFound();
});

test('exact source passages can support contradict or contextualize a finding and become stale after edits', function (string $relationship) {
    $review = plsReview();
    $document = Document::factory()->create(['pls_review_id' => $review->id]);
    $text = 'The authority must publish its annual implementation report.';
    $chunk = $document->chunks()->create(['chunk_index' => 0, 'content' => $text, 'token_count' => 15]);
    $finding = Finding::factory()->create(['pls_review_id' => $review->id]);
    $component = Livewire::test(SourcePage::class, ['review' => $review, 'document' => $document])
        ->set('findingId', (string) $finding->id)->set('passage', $text)->set('relationship', $relationship)
        ->set('note', 'Check implementation against this duty.')->call('linkPassage')->assertHasNoErrors();
    $citation = $finding->citations()->sole();
    expect($citation->isCurrent())->toBeTrue();
    expect($citation->relationship)->toBe($relationship);
    $finding->update(['detail' => 'Changed conclusion']);
    expect($citation->fresh()->isCurrent())->toBeFalse();
    $component->set('passage', $text)->call('linkPassage')->assertHasNoErrors();
    $citation = $finding->citations()->latest()->first();
    expect($citation->isCurrent())->toBeTrue();
    $chunk->update(['content' => 'The source was replaced.']);
    expect($citation->fresh()->isCurrent())->toBeFalse();
})->with(['supports', 'contradicts', 'context']);

test('fabricated quotations cannot become checked support', function () {
    $review = plsReview();
    $document = Document::factory()->create(['pls_review_id' => $review->id]);
    $finding = Finding::factory()->create(['pls_review_id' => $review->id]);
    Livewire::test(SourcePage::class, ['review' => $review, 'document' => $document])
        ->set('findingId', (string) $finding->id)->set('passage', 'This invented claim is not in the document.')
        ->call('linkPassage')->assertHasErrors('passage');
    expect($finding->citations()->count())->toBe(0);
});

test('viewers can read sources but cannot classify them or attach support', function (string $method) {
    $review = plsReview();
    $document = Document::factory()->create(['pls_review_id' => $review->id]);
    $viewer = User::factory()->reviewer()->create();
    $review->memberships()->create(['user_id' => $viewer->id, 'role' => PlsReviewMembershipRole::Viewer]);
    $this->actingAs($viewer);
    Livewire::test(SourcePage::class, ['review' => $review, 'document' => $document])->call($method)->assertForbidden();
})->with(['saveClassification', 'linkPassage']);

test('source classification is saved on the review document without editing shared legislation', function () {
    $review = plsReview();
    $document = Document::factory()->create(['pls_review_id' => $review->id, 'document_type' => DocumentType::LegislationText]);
    $law = Legislation::factory()->create(['source_document_id' => $document->id, 'legislation_type' => LegislationType::Act]);
    $review->legislation()->attach($law, ['relationship_type' => 'primary']);
    $otherReview = plsReview();
    $otherReview->legislation()->attach($law, ['relationship_type' => 'primary']);
    Livewire::test(SourcePage::class, ['review' => $review, 'document' => $document])
        ->set('classification', 'parliamentary_debate')->call('saveClassification')->assertHasNoErrors();
    expect(data_get($document->fresh()->metadata, 'source_review.classification'))->toBe('parliamentary_debate');
    expect($law->fresh()->legislation_type)->toBe(LegislationType::Act);
    expect($review->legislation()->first()->pivot->relationship_type)->toBe('related');
    expect($otherReview->legislation()->first()->pivot->relationship_type)->toBe('primary');
    $this->get(route('pls.reviews.legislation', $review))->assertOk()->assertSee('Parliamentary Debate');
});

test('source identity recognizes explicit headings without treating a passing bill reference as an instrument', function (string $title, string $text, ?LegislationType $expected) {
    expect(app(LegislationSourceIdentity::class)->classify($title, $text))->toBe($expected);
})->with([
    ['Public Procurement Bill, 2024', 'BE IT ENACTED by Parliament', LegislationType::Bill],
    ['Hansard', 'Debate concerning the new Act', LegislationType::ParliamentaryDebate],
    ['Public Procurement Act', 'This Act implements reforms discussed in the bill committee.', null],
]);

test('legislation enrichment preserves bill and debate identity despite contradictory model output', function (string $title, LegislationType $expected) {
    $document = Document::factory()->create(['title' => $title, 'document_type' => DocumentType::LegislationText]);
    LegislationSourceExtractorAgent::fake([['title' => $title, 'legislation_type' => 'act', 'date_enacted' => '2024-01-01', 'relationship_type' => 'primary']]);
    $result = app(AnalyzeLegislationSource::class)->enrich($document, 1, $title."\nSome source text.");
    expect($result['legislation_type'])->toBe($expected->value)->and($result['date_enacted'])->toBe('');
    expect($result['relationship_type'])->toBe($expected === LegislationType::ParliamentaryDebate ? 'related' : 'primary');
})->with([['Public Procurement Bill, 2024', LegislationType::Bill], ['Hansard', LegislationType::ParliamentaryDebate]]);

test('textract retains partial coverage and page warnings across response pages', function () {
    config()->set('filesystems.disks.coverage-s3', ['driver' => 's3', 'bucket' => 'test-bucket']);
    $document = Document::factory()->create(['metadata' => ['disk' => 'coverage-s3', 'extraction' => ['textract_job_id' => 'coverage-job']]]);
    $client = Mockery::mock(TextractClient::class);
    $client->shouldReceive('getDocumentTextDetection')->once()->with(['JobId' => 'coverage-job'])->andReturn(new Result([
        'JobStatus' => 'PARTIAL_SUCCESS', 'DocumentMetadata' => ['Pages' => 3], 'Warnings' => [['ErrorCode' => 'PAGE_WARNING', 'Pages' => [2]]],
        'Blocks' => [['BlockType' => 'LINE', 'Page' => 1, 'Text' => 'Opening provision']], 'NextToken' => 'next',
    ]));
    $client->shouldReceive('getDocumentTextDetection')->once()->with(['JobId' => 'coverage-job', 'NextToken' => 'next'])->andReturn(new Result([
        'JobStatus' => 'PARTIAL_SUCCESS', 'DocumentMetadata' => ['Pages' => 3], 'Blocks' => [['BlockType' => 'LINE', 'Page' => 3, 'Text' => 'Closing schedule']],
    ]));
    $result = (new TextractPdfExtractor($client))->extract($document);
    expect($result->content)->toContain('Opening provision', 'Closing schedule');
    expect($result->metadata['page_coverage'])->toMatchArray(['total_pages' => 3, 'pages_with_text' => [1, 3], 'job_status' => 'PARTIAL_SUCCESS']);
    app(RecordDocumentExtraction::class)->record($document, ['extraction_metadata' => $result->metadata, 'extraction_driver' => 'textract', 'extraction_method' => 'OCR']);
    expect($document->fresh()->pageCoverageLabel())->toContain('partial success', '2 of 3', 'does not verify');
});

test('obsolete extraction failure callbacks cannot overwrite a newer retry run', function () {
    $review = plsReview();
    $document = Document::factory()->create(['pls_review_id' => $review->id, 'document_type' => DocumentType::PolicyReport, 'summary' => 'Existing human summary']);
    $state = app(PersistReviewDocumentState::class);
    $state->markQueued($document);
    $old = new ProcessReviewDocument($document->id);
    $state->resetForRetry($document->fresh());
    $old->failed(new RuntimeException('Old worker failed'));
    expect(data_get($document->fresh()->metadata, 'extraction.status'))->toBe('queued');
    $current = new ProcessReviewDocument($document->id);
    $current->failed(new RuntimeException('Current worker failed'));
    expect(data_get($document->fresh()->metadata, 'extraction.status'))->toBe('failed');
    expect($document->fresh()->summary)->toBe('Existing human summary');
});

test('shared legislation never exposes another reviews private source text or classification', function () {
    $privateReview = plsReview();
    $review = plsReview();
    $document = Document::factory()->create([
        'pls_review_id' => $privateReview->id,
        'document_type' => DocumentType::LegislationText,
        'metadata' => ['source_review' => ['classification' => 'parliamentary_debate'], 'legislation_analysis' => ['notable_excerpts' => ['PRIVATE SOURCE QUOTATION']]],
    ]);
    $document->chunks()->create(['chunk_index' => 0, 'content' => 'PRIVATE SOURCE FULL TEXT', 'token_count' => 5]);
    $law = Legislation::factory()->create(['source_document_id' => $document->id, 'legislation_type' => LegislationType::Act]);
    $review->legislation()->attach($law, ['relationship_type' => 'primary']);
    $context = app(ReviewAssistantContextBuilder::class)->build($review, 'legislation');
    expect($context['context'])->not->toContain('PRIVATE SOURCE')->not->toContain('Parliamentary Debate');
    expect($context['context'])->toContain($law->title);
});

test('a team source check becomes stale when extraction replaces its text', function () {
    $review = plsReview();
    $document = Document::factory()->create(['pls_review_id' => $review->id]);
    $chunker = app(ChunkDocumentText::class);
    $chunker->chunk($document, 'Original reviewed text.');
    Livewire::test(SourcePage::class, ['review' => $review, 'document' => $document])->call('markChecked')->assertHasNoErrors();
    expect($document->fresh()->teamCheckLabel())->toBe('Team check recorded');
    $chunker->chunk($document, 'New extracted text.');
    expect($document->fresh()->teamCheckLabel())->toBe('Team check needs updating: text changed');
});

test('a retry during extraction or enrichment prevents obsolete results and successor jobs', function (bool $legislation, string $step) {
    Bus::fake();
    $review = plsReview();
    $document = Document::factory()->create(['pls_review_id' => $review->id, 'document_type' => $legislation ? DocumentType::LegislationText : DocumentType::ImplementationReport]);
    $state = app($legislation ? PersistLegislationSourceState::class : PersistReviewDocumentState::class);
    $state->markQueued($document);
    $jobClass = $legislation ? ProcessReviewLegislationSource::class : ProcessReviewDocument::class;
    $job = new $jobClass($document->id, $step);
    $analyzeClass = $legislation ? AnalyzeLegislationSource::class : AnalyzeReviewDocument::class;
    $analyze = Mockery::mock($analyzeClass);
    $rotate = function () use ($state, $document): array {
        $state->resetForRetry($document->fresh());

        return ['status' => 'completed', 'raw_text' => 'OBSOLETE SOURCE TEXT', 'title' => 'Obsolete title'];
    };
    if ($step === 'enrich') {
        $analyze->shouldReceive('extract')->once()->andReturn(['status' => 'completed', 'raw_text' => 'Source text']);
        $analyze->shouldReceive('enrich')->once()->andReturnUsing($rotate);
    } else {
        $analyze->shouldReceive('extract')->once()->andReturnUsing($rotate);
    }
    $job->handle($analyze, $state, app(ChunkDocumentText::class));
    $fresh = $document->fresh();
    expect(data_get($fresh->metadata, 'extraction.run_id'))->not->toBe($job->runId);
    expect(data_get($fresh->metadata, 'extraction.status'))->toBe('queued');
    expect($fresh->title)->not->toBe('Obsolete title');
    expect($fresh->chunks()->exists())->toBeFalse();
    Bus::assertNothingDispatched();
})->with([[false, 'extract'], [true, 'extract'], [false, 'enrich'], [true, 'enrich']]);
