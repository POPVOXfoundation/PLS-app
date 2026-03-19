<?php

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
use App\Livewire\Pls\Reviews\Show as ShowReviewPage;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('existing legislation can be attached from the review workspace', function () {
    $review = plsReview([
        'title' => 'Review of procurement oversight',
    ]);

    $legislation = Legislation::factory()->create([
        'jurisdiction_id' => $review->jurisdiction_id,
        'title' => 'Public Procurement Act',
        'legislation_type' => LegislationType::Act,
    ]);

    Livewire::test(ShowReviewPage::class, ['review' => $review])
        ->set('attachLegislationId', (string) $legislation->id)
        ->set('attachLegislationRelationshipType', ReviewLegislationRelationshipType::Primary->value)
        ->call('attachLegislation')
        ->assertHasNoErrors()
        ->assertSee('Public Procurement Act');

    $this->assertDatabaseHas('pls_review_legislation', [
        'pls_review_id' => $review->id,
        'legislation_id' => $legislation->id,
        'relationship_type' => ReviewLegislationRelationshipType::Primary->value,
    ]);
});

test('legislation can be created and attached from the review workspace', function () {
    $review = plsReview([
        'title' => 'Review of access to information implementation',
    ]);

    Livewire::test(ShowReviewPage::class, ['review' => $review])
        ->set('newLegislationTitle', 'Access to Information Act')
        ->set('newLegislationShortTitle', 'ATI Act')
        ->set('newLegislationType', LegislationType::Act->value)
        ->set('newLegislationDateEnacted', '2010-05-04')
        ->set('newLegislationSummary', 'Establishes public rights to request government information.')
        ->set('newLegislationRelationshipType', ReviewLegislationRelationshipType::Primary->value)
        ->call('createLegislation')
        ->assertHasNoErrors()
        ->assertSee('Access to Information Act');

    $legislation = Legislation::query()->where('title', 'Access to Information Act')->firstOrFail();

    expect($legislation->jurisdiction_id)->toBe($review->jurisdiction_id);

    $this->assertDatabaseHas('pls_review_legislation', [
        'pls_review_id' => $review->id,
        'legislation_id' => $legislation->id,
        'relationship_type' => ReviewLegislationRelationshipType::Primary->value,
    ]);
});

test('document metadata can be added from the review workspace', function () {
    $review = plsReview([
        'title' => 'Review of delegated powers reporting',
    ]);

    Livewire::test(ShowReviewPage::class, ['review' => $review])
        ->set('documentTitle', 'Review Group Briefing Pack')
        ->set('documentType', DocumentType::GroupReport->value)
        ->set('documentStoragePath', 'documents/review-group-briefing-pack.pdf')
        ->set('documentMimeType', 'application/pdf')
        ->set('documentFileSize', '120000')
        ->set('documentSummary', 'Working pack covering delegated powers, reporting delays, and evidence priorities.')
        ->call('storeDocument')
        ->assertHasNoErrors()
        ->assertSee('Review Group Briefing Pack');

    $this->assertDatabaseHas('documents', [
        'pls_review_id' => $review->id,
        'title' => 'Review Group Briefing Pack',
        'document_type' => DocumentType::GroupReport->value,
        'storage_path' => 'documents/review-group-briefing-pack.pdf',
    ]);
});

test('review documents can be uploaded, edited, and deleted from the review workspace', function () {
    Storage::fake(config('filesystems.default'));

    $review = plsReview([
        'title' => 'Review of implementation reporting files',
    ]);

    $uploadedFile = UploadedFile::fake()->create('implementation-brief.pdf', 256, 'application/pdf');

    $component = Livewire::test(ShowReviewPage::class, ['review' => $review])
        ->set('documentTitle', 'Implementation brief')
        ->set('documentType', DocumentType::GroupReport->value)
        ->set('documentUpload', $uploadedFile)
        ->set('documentSummary', 'Initial evidence pack for the review team.')
        ->call('storeDocument')
        ->assertHasNoErrors()
        ->assertSee('Implementation brief');

    /** @var Document $document */
    $document = $review->fresh()->documents()->firstOrFail();

    expect($document->storage_path)->toStartWith(sprintf('pls/reviews/%d/documents/', $review->id))
        ->and($document->mime_type)->toBe('application/pdf')
        ->and($document->file_size)->toBeGreaterThan(0);

    Storage::disk(config('filesystems.default'))->assertExists($document->storage_path);

    $replacementFile = UploadedFile::fake()->create('implementation-brief-v2.pdf', 128, 'application/pdf');

    $component
        ->call('startEditingDocument', $document->id)
        ->set('documentTitle', 'Implementation brief v2')
        ->set('documentUpload', $replacementFile)
        ->set('documentSummary', 'Updated evidence pack with agency annexes.')
        ->call('updateDocument')
        ->assertHasNoErrors()
        ->assertSee('Implementation brief v2');

    $updatedDocument = $document->fresh();

    expect($updatedDocument->title)->toBe('Implementation brief v2')
        ->and($updatedDocument->summary)->toBe('Updated evidence pack with agency annexes.')
        ->and($updatedDocument->storage_path)->not->toBe($document->storage_path);

    Storage::disk(config('filesystems.default'))->assertMissing($document->storage_path);
    Storage::disk(config('filesystems.default'))->assertExists($updatedDocument->storage_path);

    $this->assertDatabaseHas('documents', [
        'id' => $updatedDocument->id,
    ]);

    $component
        ->call('confirmDeletion', 'document', $updatedDocument->id)
        ->assertHasNoErrors()
        ->assertDontSee('Implementation brief v2');

    $this->assertDatabaseMissing('documents', [
        'id' => $updatedDocument->id,
    ]);

    Storage::disk(config('filesystems.default'))->assertMissing($updatedDocument->storage_path);
});

test('findings and recommendations can be added from the review workspace', function () {
    $review = plsReview([
        'title' => 'Review of implementation bottlenecks',
    ]);

    $component = Livewire::test(ShowReviewPage::class, ['review' => $review])
        ->set('findingTitle', 'Agency reporting remains inconsistent')
        ->set('findingSummary', 'Quarterly implementation reports are incomplete across multiple agencies.')
        ->call('storeFinding')
        ->assertHasNoErrors()
        ->assertSee('Agency reporting remains inconsistent');

    $findingId = (string) $review->fresh()->findings()->where('title', 'Agency reporting remains inconsistent')->value('id');

    $component
        ->set('recommendationFindingId', $findingId)
        ->set('recommendationTitle', 'Mandate a standard reporting template')
        ->set('recommendationDescription', 'Require a common quarterly template and review-group compliance review.')
        ->call('storeRecommendation')
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

    $component = Livewire::test(ShowReviewPage::class, ['review' => $review])
        ->call('startEditingFinding', $finding->id)
        ->set('findingTitle', 'Agency reporting standards remain inconsistent')
        ->set('findingSummary', 'Reporting formats still vary significantly across implementing agencies.')
        ->call('updateFinding')
        ->assertHasNoErrors()
        ->assertSee('Agency reporting standards remain inconsistent');

    $component
        ->call('startEditingRecommendation', $recommendation->id)
        ->set('recommendationTitle', 'Adopt a common quarterly reporting template')
        ->set('recommendationDescription', 'Issue a single template and require review-group compliance tracking.')
        ->call('updateRecommendation')
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

test('reports can be created from the review workspace and linked to a review document', function () {
    $review = plsReview([
        'title' => 'Review of publication and dissemination obligations',
    ]);

    $document = Document::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Draft dissemination report',
        'document_type' => DocumentType::DraftReport,
    ]);

    Livewire::test(ShowReviewPage::class, ['review' => $review])
        ->set('reportTitle', 'Draft PLS Report on Dissemination Obligations')
        ->set('reportType', ReportType::DraftReport->value)
        ->set('reportStatus', ReportStatus::Published->value)
        ->set('reportDocumentId', (string) $document->id)
        ->set('reportPublishedAt', '2026-03-10')
        ->call('storeReport')
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

    $component = Livewire::test(ShowReviewPage::class, ['review' => $review])
        ->call('startEditingReport', $report->id)
        ->set('reportTitle', 'Final dissemination report record')
        ->set('reportType', ReportType::FinalReport->value)
        ->set('reportStatus', ReportStatus::Published->value)
        ->set('reportDocumentId', (string) $document->id)
        ->set('reportPublishedAt', '2026-03-11')
        ->call('updateReport')
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

        Livewire::test(ShowReviewPage::class, ['review' => $review])
            ->call('prepareReportCreate', ReportType::FinalReport->value, ReportStatus::Published->value)
            ->assertSet('reportType', ReportType::FinalReport->value)
            ->assertSet('reportStatus', ReportStatus::Published->value)
            ->assertSet('reportPublishedAt', '2026-03-18')
            ->call('prepareGovernmentResponseCreate', null, GovernmentResponseStatus::Received->value)
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

    Livewire::test(ShowReviewPage::class, ['review' => $review])
        ->set('governmentResponseReportId', (string) $report->id)
        ->set('governmentResponseDocumentId', (string) $document->id)
        ->set('governmentResponseStatus', GovernmentResponseStatus::Received->value)
        ->set('governmentResponseReceivedAt', '2026-03-11')
        ->set('governmentResponseSummary', 'Cabinet accepted the primary recommendation and requested a six-month implementation update.')
        ->call('storeGovernmentResponse')
        ->assertHasNoErrors()
        ->assertSee('Government response recorded for this review.')
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

    Livewire::test(ShowReviewPage::class, ['review' => $review])
        ->assertSee('Reporting workspace')
        ->assertSee('Drafting inputs from analysis')
        ->assertSee('Mandate a standard reporting template')
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

    Livewire::test(ShowReviewPage::class, ['review' => $review])
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

    Livewire::test(ShowReviewPage::class, ['review' => $review->fresh()])
        ->assertSee('Current workspace focus')
        ->assertSee('Track what happens after publication')
        ->assertSee('Best next area: Reports')
        ->assertSee('Keep the report record current and log any response request, reply, or overdue follow-up.');
});
