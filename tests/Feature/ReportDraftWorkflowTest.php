<?php

use App\Ai\Agents\ReviewAssistantAgent;
use App\Domain\Analysis\Finding;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Legislation\Legislation;
use App\Domain\Legislation\LegislationObjective;
use App\Domain\Reporting\Actions\QueueReportDraft;
use App\Domain\Reporting\Actions\UpdateReport;
use App\Domain\Reporting\Report;
use App\Domain\Reporting\Validation\UpdateReportValidator;
use App\Domain\Reviews\PlsReview;
use App\Domain\Stakeholders\ImplementingAgency;
use App\Jobs\GenerateReviewReportDraft;
use App\Livewire\Pls\Reviews\ReportEditorPage;
use App\Models\User;
use App\Support\Reports\FullReviewReportContext;
use App\Support\Reports\ReportDocumentExporter;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->reviewer()->create());
});

test('full report context contains every finding and closing source passage without using chat excerpts', function () {
    $review = plsReview();
    $report = Report::factory()->create(['pls_review_id' => $review->id, 'document_id' => null]);
    foreach (range(1, 9) as $i) {
        Finding::factory()->create(['pls_review_id' => $review->id, 'detail' => "Complete saved finding $i"]);
    }
    $source = Document::factory()->create(['pls_review_id' => $review->id, 'document_type' => DocumentType::LegislationText]);
    $source->chunks()->create(['chunk_index' => 0, 'content' => 'Beginning of source', 'token_count' => 5]);
    $source->chunks()->create(['chunk_index' => 1, 'content' => 'Closing schedule duty', 'token_count' => 5]);
    $context = app(FullReviewReportContext::class)->build($report, null);
    expect($context)->toContain('Complete saved finding 9', 'Closing schedule duty', route('pls.reviews.documents.show', [$review, $source]));
});

test('report generation stores a complete saved draft and version', function () {
    $dispatcher = Bus::getFacadeRoot();
    Bus::fake();
    $review = plsReview();
    $report = app(QueueReportDraft::class)->queue($review);
    Bus::assertDispatched(GenerateReviewReportDraft::class);
    ReviewAssistantAgent::fake(['## Executive summary'."\n\n".'Provisional result with limitations.']);
    $job = new GenerateReviewReportDraft($report->id, $report->generation_token, 0);
    if (getenv('PLS_QUEUE_ACCEPTANCE') === 'redis') {
        Bus::swap($dispatcher);
        $queueName = 'codex-pls-acceptance-'.Str::uuid();
        $job->onQueue($queueName);
        $connection = app('queue')->connection('redis');
        try {
            $connection->push($job, queue: $queueName);
            Artisan::call('queue:work', ['connection' => 'redis', '--queue' => $queueName, '--once' => true, '--stop-when-empty' => true, '--sleep' => 0, '--tries' => 1]);
        } finally {
            $connection->clear($queueName);
        }
    } else {
        app()->call([$job, 'handle']);
    }
    expect($report->fresh()->generation_status)->toBe('completed');
    expect($report->fresh()->content)->toContain('Provisional result');
    expect($report->versions()->count())->toBe(2);
    Livewire::test(ReportEditorPage::class, ['review' => $review, 'report' => $report->fresh()])->assertSee('Provisional result');
});

test('queue dispatch failure leaves a retryable failed report', function () {
    Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Queue disconnected'));
    $report = app(QueueReportDraft::class)->queue(plsReview());
    expect($report->generation_status)->toBe('failed')->and($report->generation_error)->toContain('preserved');
});

test('editing a queued report causes generation to finish as failed without an AI call', function () {
    Bus::fake();
    $review = plsReview();
    $report = app(QueueReportDraft::class)->queue($review);
    $report->update(['content' => 'Human edit', 'revision' => 1]);
    ReviewAssistantAgent::fake();
    app()->call([new GenerateReviewReportDraft($report->id, $report->generation_token, 0), 'handle']);
    expect($report->fresh()->content)->toBe('Human edit')->and($report->fresh()->generation_status)->toBe('failed');
    ReviewAssistantAgent::assertNeverPrompted();
});

test('obsolete generation and failure callbacks cannot alter a newer request', function () {
    Bus::fake();
    $report = app(QueueReportDraft::class)->queue(plsReview());
    $job = new GenerateReviewReportDraft($report->id, $report->generation_token, 0);
    $report->update(['generation_token' => '00000000-0000-4000-8000-000000000003', 'content' => 'Newer draft']);
    $job->failed(null);
    app()->call([$job, 'handle']);
    expect($report->fresh()->generation_status)->toBe('queued')->and($report->fresh()->content)->toBe('Newer draft');
});

test('oversized report input fails visibly without silently omitting text', function () {
    Bus::fake();
    config()->set('pls_assistant.max_context_characters', 10);
    $report = app(QueueReportDraft::class)->queue(plsReview());
    ReviewAssistantAgent::fake();
    app()->call([new GenerateReviewReportDraft($report->id, $report->generation_token, 0), 'handle']);
    expect($report->fresh()->generation_status)->toBe('failed')->and($report->fresh()->generation_error)->toContain('No source text was silently shortened');
    ReviewAssistantAgent::assertNeverPrompted();
});

test('report editor saves sections and rejects stale or oversized complete bodies', function () {
    $review = plsReview();
    $report = Report::factory()->create(['pls_review_id' => $review->id, 'document_id' => null, 'content' => "## Scope\nOriginal\n\n## Findings\nOriginal finding"]);
    $first = Livewire::test(ReportEditorPage::class, ['review' => $review, 'report' => $report])->call('edit');
    $second = Livewire::test(ReportEditorPage::class, ['review' => $review, 'report' => $report])->call('edit');
    $first->set('sections.1', "## Findings\nReviewed finding")->call('save')->assertHasNoErrors();
    $second->set('sections.0', 'Unsaved second edit')->call('save')->assertHasErrors('sections')->assertSet('sections.0', 'Unsaved second edit');
    expect($report->fresh()->content)->toContain('Reviewed finding', 'Original');
    $first->set('sections', [str_repeat('x', 110000), str_repeat('y', 110000)])->call('save')->assertHasErrors('sections');
    expect($report->versions()->where('revision', 0)->sole()->content)->toContain('Original finding');
});

test('report downloads export the selected saved version and enforce review boundaries', function (string $format) {
    $review = plsReview();
    $report = Report::factory()->create(['pls_review_id' => $review->id, 'document_id' => null, 'content' => 'Historical wording']);
    app(UpdateReport::class)->update(['report_id' => $report->id, 'pls_review_id' => $review->id, 'title' => $report->title, 'content' => 'New wording', 'report_type' => $report->report_type->value, 'status' => $report->status->value]);
    $url = route('pls.reviews.reports.download', [$review, $report, 'format' => $format, 'revision' => 0]);
    $response = $this->get($url)->assertOk()->assertDownload()->assertHeader('X-Content-Type-Options', 'nosniff');
    if ($format === 'md') {
        expect($response->getContent())->toContain('Historical wording')->not->toContain('New wording');
    } elseif ($format === 'pdf') {
        expect($response->getContent())->toStartWith('%PDF-');
    } else {
        $path = tempnam(sys_get_temp_dir(), 'pls-export-test-');
        file_put_contents($path, $response->getContent());
        $zip = new ZipArchive;
        $zip->open($path);
        expect($zip->getFromName('word/document.xml'))->toContain('Historical wording')->not->toContain('New wording');
        $zip->close();
        unlink($path);
    }
    $other = plsReview();
    $this->get(route('pls.reviews.reports.download', [$other, $report, 'format' => $format]))->assertNotFound();
    $this->actingAs(User::factory()->reviewer()->create())->get($url)->assertForbidden();
})->with(['pdf', 'docx', 'md']);

test('export sanitization removes active markup unsafe links and remote images', function () {
    $html = app(ReportDocumentExporter::class)->safeHtml('<script>alert(1)</script>'."\n\n![tracker](https://example.com/track)\n\n[unsafe](file:///etc/passwd)\n\n[Source](https://example.com/source)");
    expect($html)->not->toContain('<script', '<img', 'file:///')->toContain('https://example.com/source');
});

test('long formatted exports retain unicode headings lists tables and source links', function () {
    $content = "## Executive summary\n\nGhana and Nigeria — café & public accountability.\n\n## Findings\n\n- A recorded obligation\n- A limitation requiring review\n\n| Source | Finding |\n| --- | --- |\n| [Original source](https://example.com/legislation) | Human review required |\n\n";
    for ($i = 1; $i <= 18; $i++) {
        $content .= "### Finding $i\n\n".str_repeat('The review records an implementation obligation and identifies missing evidence. ', 12)."\n\n";
    }
    $content .= "## Closing schedule\n\nEnd marker: all saved text must be preserved.";
    foreach (['pdf', 'docx'] as $format) {
        $response = app(ReportDocumentExporter::class)->download($format, 'PLS report export acceptance sample', $content, 'draft', 4);
        expect(strlen($response->getContent()))->toBeGreaterThan(2000);
        if ($directory = getenv('PLS_EXPORT_QA_DIR')) {
            if (! is_dir($directory)) {
                mkdir($directory, 0700, true);
            }
            file_put_contents($directory.'/report.'.$format, $response->getContent());
        }
    }
});

test('a save retains its own revision when another writer commits before the editor refreshes', function () {
    $review = plsReview();
    $report = Report::factory()->create(['pls_review_id' => $review->id, 'document_id' => null, 'content' => 'Initial draft']);
    $interleaving = new class(app(UpdateReportValidator::class)) extends UpdateReport
    {
        public bool $interleave = true;

        public function update(array $input): PlsReview
        {
            $review = parent::update($input);
            if ($this->interleave) {
                $this->interleave = false;
                parent::update([...$input, 'expected_revision' => 1, 'content' => 'Another writer committed']);
            }

            return $review;
        }
    };
    app()->instance(UpdateReport::class, $interleaving);
    $component = Livewire::test(ReportEditorPage::class, ['review' => $review, 'report' => $report])->call('edit')
        ->set('sections', ['My first edit'])->call('save')->assertHasNoErrors()->assertSet('baseRevision', 1);
    expect($report->fresh()->revision)->toBe(2);
    $component->set('sections', ['My next edit'])->call('save')->assertHasErrors('sections');
    expect($report->fresh()->content)->toBe('Another writer committed');
});

test('the complete request limit includes instructions and section regeneration text', function () {
    Bus::fake();
    $review = plsReview();
    $report = app(QueueReportDraft::class)->queue($review);
    $record = app(FullReviewReportContext::class)->build($report, null);
    config()->set('pls_assistant.max_context_characters', mb_strlen($record) + 1);
    ReviewAssistantAgent::fake();
    app()->call([new GenerateReviewReportDraft($report->id, $report->generation_token, 0), 'handle']);
    expect($report->fresh()->generation_status)->toBe('failed')->and($report->fresh()->generation_error)->toContain('including its instructions');
    ReviewAssistantAgent::assertNeverPrompted();
});

test('selected section generation preserves the rest of the saved report', function () {
    Bus::fake();
    $review = plsReview();
    $report = Report::factory()->create(['pls_review_id' => $review->id, 'status' => 'draft', 'content' => "## Scope\nOriginal scope\n\n## Findings\nOriginal findings", 'document_id' => null]);
    Livewire::test(ReportEditorPage::class, ['review' => $review, 'report' => $report])
        ->set('sectionToRegenerate', 1)->call('regenerateSection', $report->revision)->assertHasNoErrors();
    Bus::assertDispatched(GenerateReviewReportDraft::class, fn ($job): bool => $job->section === 1);
    $report->refresh();
    ReviewAssistantAgent::fake(["## Findings\nReplacement findings"]);
    app()->call([new GenerateReviewReportDraft($report->id, $report->generation_token, $report->revision, section: 1), 'handle']);
    expect($report->fresh()->content)->toBe("## Scope\nOriginal scope\n\n## Findings\nReplacement findings");
    expect($report->versions()->count())->toBe(2);
});

test('report context includes the linked legal baseline and excludes objectives from excluded legislation', function () {
    $review = plsReview();
    $report = Report::factory()->create(['pls_review_id' => $review->id, 'document_id' => null]);
    $law = Legislation::factory()->create(['source_document_id' => null, 'title' => 'Public Services Act']);
    $excludedDocument = Document::factory()->create(['pls_review_id' => $review->id, 'metadata' => ['source_review' => ['excluded' => true]]]);
    $excludedLaw = Legislation::factory()->create(['source_document_id' => $excludedDocument->id, 'title' => 'Excluded law']);
    $review->legislation()->attach([$law->id => ['relationship_type' => 'primary'], $excludedLaw->id => ['relationship_type' => 'related']]);
    LegislationObjective::factory()->create(['pls_review_id' => $review->id, 'legislation_id' => $law->id, 'title' => 'Expand access']);
    LegislationObjective::factory()->create(['pls_review_id' => $review->id, 'legislation_id' => $excludedLaw->id, 'title' => 'Excluded objective']);
    ImplementingAgency::factory()->create(['pls_review_id' => $review->id, 'name' => 'Public Services Office']);

    $context = json_decode(app(FullReviewReportContext::class)->build($report, null), true, flags: JSON_THROW_ON_ERROR);
    expect($context['legislation'])->toHaveCount(1)
        ->and($context['legislation'][0])->toMatchArray(['title' => 'Public Services Act', 'relationship_type' => 'primary'])
        ->and($context['legislation_objectives'])->toHaveCount(1)
        ->and($context['legislation_objectives'][0])->toMatchArray(['legislation_id' => $law->id, 'title' => 'Expand access'])
        ->and($context['implementing_agencies'][0]['name'])->toBe('Public Services Office');
});

test('a stale section selection cannot regenerate a section of a newer revision', function () {
    Bus::fake();
    $review = plsReview();
    $report = Report::factory()->create([
        'pls_review_id' => $review->id, 'status' => 'draft', 'document_id' => null,
        'content' => "## Scope\nScope text\n\n## Findings\nFindings text",
    ]);
    $revision = $report->revision;
    $component = Livewire::test(ReportEditorPage::class, ['review' => $review, 'report' => $report])
        ->assertSeeHtml('wire:click="regenerateSection('.$revision.')"')->set('sectionToRegenerate', 1);
    $report->update(['revision' => $revision + 1, 'content' => "## Introduction\nNew section\n\n".$report->content]);

    $component->call('regenerateSection', $revision)->assertHasErrors('generation');
    Bus::assertNotDispatched(GenerateReviewReportDraft::class);
    expect($report->fresh()->generation_status)->toBeNull();
});

test('report legislation uses review classification and removes enactment dates from bills and debates', function (string $classification) {
    $review = plsReview();
    $report = Report::factory()->create(['pls_review_id' => $review->id, 'document_id' => null]);
    $source = Document::factory()->create([
        'pls_review_id' => $review->id,
        'document_type' => DocumentType::LegislationText,
        'metadata' => ['source_review' => ['classification' => $classification]],
    ]);
    $law = Legislation::factory()->create(['source_document_id' => $source->id, 'legislation_type' => 'act', 'date_enacted' => '2024-04-12']);
    $act = Legislation::factory()->create(['source_document_id' => null, 'legislation_type' => 'act', 'date_enacted' => '2024-05-13']);
    $review->legislation()->attach([$law->id => ['relationship_type' => 'related'], $act->id => ['relationship_type' => 'primary']]);

    $context = json_decode(app(FullReviewReportContext::class)->build($report, null), true, flags: JSON_THROW_ON_ERROR);
    $legislation = collect($context['legislation'])->keyBy('id');

    expect($legislation[$law->id]['legislation_type'])->toBe($classification)
        ->and($legislation[$law->id])->not->toHaveKey('date_enacted')
        ->and($context['sources'][0]['classification'])->toBe($classification)
        ->and($legislation[$act->id]['legislation_type'])->toBe('act')
        ->and($legislation[$act->id]['date_enacted'])->toContain('2024-05-13');
})->with(['bill', 'parliamentary_debate']);
