<?php

use App\Ai\Agents\ReviewAssistantAgent;
use App\Domain\Documents\Actions\AnalyzeReviewDocument;
use App\Domain\Documents\Actions\ChunkDocumentText;
use App\Domain\Documents\Actions\PersistReviewDocumentState;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Reporting\Report;
use App\Domain\Reviews\ReviewActivity;
use App\Jobs\GenerateReviewReport;
use App\Jobs\ProcessReviewDocument;
use App\Jobs\SendReviewInvitation;
use App\Livewire\Pls\Reviews\AssistantSidebar;
use App\Livewire\Pls\Reviews\CollaboratorsPage;
use App\Livewire\Pls\Reviews\DocumentPage;
use App\Livewire\Pls\Reviews\DocumentsPage;
use App\Livewire\Pls\Reviews\RecordIntake;
use App\Livewire\Pls\Reviews\ReportPage;
use App\Livewire\Pls\Reviews\ReportsPage;
use App\Models\User;
use App\Support\Documents\RecordSpreadsheet;
use App\Support\PlsAssistant\ReviewAssistantContextBuilder;
use App\Support\Reports\ReportExporter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Prompts\AgentPrompt;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

beforeEach(function () {
    $this->review = plsReview();
    $this->actingAs($this->review->owner);
    Queue::fake();
});

function nigeriaDraft($review, string $content = "## Scope\n\nHuman scope.\n\n## Findings\n\nHuman finding."): Report
{
    return $review->reports()->create(['title' => 'Working report', 'report_type' => 'draft_report', 'status' => 'draft', 'content' => $content]);
}

it('replaces completion bars with actual checks and exposes report and register actions', function () {
    $this->get(route('pls.reviews.workflow', $this->review))->assertOk()->assertSee('Record checks')->assertSee('Current methodology stage')->assertDontSee('Review progress')->assertDontSee('9%');
    $this->get(route('pls.reviews.reports', $this->review))->assertOk()->assertSee('Generate report')->assertSee('Start a blank draft');
    $this->get(route('pls.reviews.documents', $this->review))->assertOk()->assertSee('Complete document register')->assertSee('Preview additions');
});

it('queues report generation and saves full text including the closing law provisions', function () {
    $law = Document::factory()->create(['pls_review_id' => $this->review->id, 'document_type' => DocumentType::LegislationText]);
    $text = str_repeat("Ordinary provision.\n\n", 700).'FINAL_SCHEDULE_REQUIRES_AN_AUDIT';
    app(ChunkDocumentText::class)->chunk($law, $text);
    for ($index = 0; $index < 8; $index++) {
        $this->review->findings()->create(['title' => 'Finding '.$index, 'finding_type' => 'implementation_gap', 'summary' => 'Saved finding', 'detail' => 'FULL_SAVED_DETAIL_'.$index]);
    }
    Livewire::test(ReportsPage::class, ['review' => $this->review])->call('generateReport')->assertHasNoErrors();
    $report = $this->review->reports()->firstOrFail();
    expect($report->generation_status)->toBe('queued');
    Queue::assertPushed(GenerateReviewReport::class);
    ReviewAssistantAgent::fake(["## Executive summary\n\nA provisional report.\n\n## Limitations\n\nMissing consultation results."]);
    (new GenerateReviewReport($report->id, 0))->handle(app(ReviewAssistantContextBuilder::class));
    expect($report->fresh()->generation_status)->toBe('completed')->and($report->fresh()->content)->toContain('Source register', route('pls.reviews.documents.show', [$this->review, $law]));
    ReviewAssistantAgent::assertPrompted(fn (AgentPrompt $prompt) => str_contains((string) $prompt->agent->instructions(), $text) && str_contains((string) $prompt->agent->instructions(), 'FULL_SAVED_DETAIL_7'));
});

it('preserves human edits when generation finishes after a newer save', function () {
    $report = nigeriaDraft($this->review);
    $report->update(['generation_status' => 'queued']);
    ReviewAssistantAgent::fake(function () use ($report) {
        $report->fresh()->update(['content' => 'A newer human version.']);

        return 'AI overwrite';
    });
    (new GenerateReviewReport($report->id, 0))->handle(app(ReviewAssistantContextBuilder::class));
    expect($report->fresh()->content)->toBe('A newer human version.')->and($report->fresh()->generation_status)->toBe('failed');
});

it('regenerates only the requested section and retains version history', function () {
    $report = nigeriaDraft($this->review);
    $report->update(['generation_status' => 'queued']);
    ReviewAssistantAgent::fake(["## Findings\n\nReplacement finding."]);
    (new GenerateReviewReport($report->id, 0, null, 1))->handle(app(ReviewAssistantContextBuilder::class));
    expect($report->fresh()->content)->toContain('Human scope.', 'Replacement finding.')->not->toContain('Human finding.');
    expect($report->fresh()->content_versions[0]['content'])->toContain('Human finding.');
});

it('detects concurrent report editing without losing either version', function () {
    $report = nigeriaDraft($this->review);
    $editor = Livewire::test(ReportPage::class, ['review' => $this->review, 'report' => $report])->call('editDraft');
    $report->update(['content' => 'Another contributor saved this.']);
    $editor->set('sections.0', 'Unsaved local text')->call('saveDraft')->assertHasErrors('sections')->assertSet('sections.0', 'Unsaved local text');
    expect($report->fresh()->content)->toBe('Another contributor saved this.');
});

it('exports the selected version as editable Word and PDF with safe content', function () {
    $report = nigeriaDraft($this->review, "## Original version\n\nOriginal text with [source](https://example.org/document).");
    $report->update(['content' => 'Replacement current draft']);
    $pdf = $this->get(route('pls.reviews.reports.export', [$this->review, $report, 'format' => 'pdf', 'version' => 1]))->assertOk()->assertHeader('Content-Type', 'application/pdf')->getContent();
    expect($pdf)->toStartWith('%PDF');
    $word = $this->get(route('pls.reviews.reports.export', [$this->review, $report, 'format' => 'docx', 'version' => 1]))->assertOk()->getContent();
    $path = tempnam(sys_get_temp_dir(), 'pls-test-docx-');
    file_put_contents($path, $word);
    $zip = new ZipArchive;
    $zip->open($path);
    expect($zip->getFromName('word/document.xml'))->toContain('Original text')->not->toContain('Replacement current draft');
    $zip->close();
    unlink($path);
    expect(app(ReportExporter::class)->html('Title', "<script>alert(1)</script>\n\n![remote](http://127.0.0.1/secret)"))->not->toContain('<script', '<img');
    $foreign = nigeriaDraft(plsReview());
    $this->get(route('pls.reviews.reports.export', [$this->review, $foreign, 'format' => 'pdf']))->assertNotFound();
});

it('imports matching files and source links after a preview and reuses submission documents', function () {
    Storage::fake('local');
    $csv = "title,filename,url,type,date,organisation,stakeholder,notes\nSubmission,submission.txt,,consultation_submission,2026-11-01,Research Group,Research Group,Interview findings\nContext,,https://example.org/context,policy_report,,,,Read full text later\n";
    Livewire::test(RecordIntake::class, ['review' => $this->review])
        ->set('files', [UploadedFile::fake()->createWithContent('submission.txt', 'Interview text')])
        ->set('spreadsheet', UploadedFile::fake()->createWithContent('record.csv', $csv))
        ->call('previewImport')->assertHasNoErrors()->assertSee('Ready to add')->call('import')->assertHasNoErrors();
    expect($this->review->documents()->count())->toBe(2)->and($this->review->submissions()->count())->toBe(1);
    $link = $this->review->documents()->where('title', 'Context')->firstOrFail();
    expect($link->processingStatus())->toBe('uploaded')->and($link->storage_path)->toBeNull()->and($link->originalUrl())->toBe('https://example.org/context');
    expect($this->review->submissions()->first()->document->title)->toBe('Submission');
    Queue::assertPushed(ProcessReviewDocument::class, 1);
});

it('rejects unmatched spreadsheet files and unsafe links before creating records', function () {
    $csv = "title,filename,url,type,date,organisation,stakeholder,notes\nBroken,missing.txt,javascript:alert(1),group_report,,,,\n";
    Livewire::test(RecordIntake::class, ['review' => $this->review])->set('spreadsheet', UploadedFile::fake()->createWithContent('record.csv', $csv))->call('previewImport')->assertSee('Choose exactly one file matching this filename.')->call('import')->assertHasErrors('files');
    expect($this->review->documents()->count())->toBe(0);
});

it('shows a validation message for a corrupt workbook instead of a server error', function () {
    $path = tempnam(sys_get_temp_dir(), 'pls-corrupt-xlsx-');
    file_put_contents($path, 'This is not a workbook.');
    try {
        expect(fn () => app(RecordSpreadsheet::class)->read($path, 'xlsx'))->toThrow(ValidationException::class);
    } finally {
        unlink($path);
    }
});

it('marks duplicate imports for explicit review and prevents an accidental repeat', function () {
    $this->review->documents()->create(['title' => 'Existing', 'document_type' => 'policy_report', 'metadata' => ['source_url' => 'https://example.org/original']]);
    Livewire::test(RecordIntake::class, ['review' => $this->review])->set('url', 'https://example.org/original')->set('title', 'Existing')->call('previewImport')->assertSee('Possible duplicate')->call('import')->assertHasErrors('files');
    expect($this->review->documents()->count())->toBe(1);
});

it('attaches an original to a reference without creating a second document', function () {
    Storage::fake('local');
    $doc = $this->review->documents()->create(['title' => 'Reference', 'document_type' => 'policy_report', 'metadata' => ['source_url' => 'https://example.org/source']]);
    Livewire::test(DocumentPage::class, ['review' => $this->review, 'document' => $doc])->set('sourceFile', UploadedFile::fake()->createWithContent('source.txt', 'Full source text'))->call('attachOriginal')->assertHasNoErrors();
    expect($this->review->documents()->count())->toBe(1)->and($doc->fresh()->processingStatus())->toBe('queued');
});

it('edits reference metadata without requiring a file or dropping the original link', function () {
    $doc = $this->review->documents()->create(['title' => 'Reference', 'document_type' => 'policy_report', 'metadata' => ['source_url' => 'https://example.org/source']]);
    Livewire::test(DocumentsPage::class, ['review' => $this->review])->call('startEditingDocument', $doc->id)->set('documentTitle', 'Updated reference')->call('saveDocumentEdits')->assertHasNoErrors();
    expect($doc->fresh()->title)->toBe('Updated reference')->and($doc->fresh()->originalUrl())->toBe('https://example.org/source')->and($doc->fresh()->storage_path)->toBeNull();
});

it('exports every document type and neutralizes spreadsheet formulas', function () {
    Document::factory()->create(['pls_review_id' => $this->review->id, 'title' => '=HYPERLINK("malicious")', 'document_type' => DocumentType::LegislationText]);
    $csv = $this->get(route('pls.reviews.documents.register', $this->review))->assertOk()->getContent();
    expect($csv)->toContain("'=HYPERLINK", 'legislation_text', 'Not checked');
    $this->get(route('pls.reviews.documents.register', [$this->review, 'format' => 'xlsx']))->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

it('excludes an explicitly excluded law from assistant grounding', function () {
    $law = Document::factory()->create(['pls_review_id' => $this->review->id, 'document_type' => DocumentType::LegislationText, 'metadata' => ['human_review' => ['excluded' => true]]]);
    app(ChunkDocumentText::class)->chunk($law, 'EXCLUDED_LAW_SECRET_TEXT');
    expect(app(ReviewAssistantContextBuilder::class)->build($this->review, 'reports')['context'])->not->toContain('EXCLUDED_LAW_SECRET_TEXT');
});

it('queues invitations for existing accounts and grants access only on acceptance', function () {
    $invitee = User::factory()->create();
    Livewire::test(CollaboratorsPage::class, ['review' => $this->review])->set('inviteCollaboratorEmail', $invitee->email)->call('shareReview')->assertHasNoErrors();
    $invitation = $this->review->invitations()->firstOrFail();
    expect($invitation->mail_status)->toBe('queued')->and($this->review->memberships()->where('user_id', $invitee->id)->exists())->toBeFalse();
    Queue::assertPushed(SendReviewInvitation::class);
    $this->actingAs($invitee)->get(route('pls.invitations.accept', $invitation->token))->assertRedirect();
    $this->get(route('pls.invitations.accept', $invitation->token))->assertRedirect();
    expect($this->review->memberships()->where('user_id', $invitee->id)->count())->toBe(1)->and($invitation->fresh()->accepted_at)->not->toBeNull();
});

it('tracks mail transport status, expired invitations and revoked links honestly', function () {
    Notification::fake();
    config(['mail.default' => 'smtp', 'mail.mailers.smtp.transport' => 'smtp']);
    $invitation = $this->review->invitations()->create(['email' => 'person@example.org', 'role' => 'viewer', 'invited_by' => auth()->id(), 'mail_status' => 'queued', 'expires_at' => now()->addDay()]);
    $job = new SendReviewInvitation($invitation->id, $invitation->token);
    $job->handle();
    expect($invitation->fresh()->mail_status)->toBe('sent');
    $job->failed(new RuntimeException('transport failed'));
    expect($invitation->fresh()->mail_status)->toBe('failed');
    $person = User::factory()->create(['email' => 'person@example.org']);
    $invitation->update(['expires_at' => now()->subDay()]);
    $this->actingAs($person)->get(route('pls.invitations.accept', $invitation->token))->assertStatus(410);
    $token = $invitation->token;
    $invitation->delete();
    $this->get(route('pls.invitations.accept', $token))->assertNotFound();
});

it('keeps activity scoped to shared record changes and protects mutations from viewers', function () {
    $document = Document::factory()->create(['pls_review_id' => $this->review->id, 'title' => 'Shared source']);
    expect(ReviewActivity::where('pls_review_id', $this->review->id)->where('subject_type', 'Document')->where('title', 'Shared source')->exists())->toBeTrue();
    $viewer = User::factory()->create();
    $this->review->memberships()->create(['user_id' => $viewer->id, 'role' => 'viewer']);
    $this->actingAs($viewer);
    Livewire::test(ReportsPage::class, ['review' => $this->review])->call('generateReport')->assertForbidden();
    Livewire::test(RecordIntake::class, ['review' => $this->review])->call('previewImport')->assertForbidden();
    Livewire::test(DocumentPage::class, ['review' => $this->review, 'document' => $document])->call('setExcluded', true)->assertForbidden();
});

it('does not let an obsolete generation job replace a newer queued request', function () {
    $report = nigeriaDraft($this->review);
    $report->update(['generation_status' => 'queued']);
    $obsolete = new GenerateReviewReport($report->id, 0);
    $report->update(['generation_token' => (string) Str::uuid()]);
    ReviewAssistantAgent::fake(['Old response']);
    $obsolete->handle(app(ReviewAssistantContextBuilder::class));
    $obsolete->failed(new RuntimeException('Old worker timed out'));
    ReviewAssistantAgent::assertNeverPrompted();
    expect($report->fresh()->generation_status)->toBe('queued')->and($report->fresh()->content)->toContain('Human scope.');
});

it('stops document polling after its limit and exposes a retryable failure', function () {
    $doc = Document::factory()->create(['pls_review_id' => $this->review->id, 'document_type' => DocumentType::GroupReport, 'metadata' => ['extraction' => ['status' => 'processing', 'poll_attempts' => 60, 'queued_at' => now()->toIso8601String()]]]);
    $analyze = Mockery::mock(AnalyzeReviewDocument::class);
    $analyze->shouldNotReceive('extract');
    (new ProcessReviewDocument($doc->id))->handle($analyze, app(PersistReviewDocumentState::class), app(ChunkDocumentText::class));
    expect($doc->fresh()->processingStatus())->toBe('needs_attention');
    Queue::assertNothingPushed();
    expect(ReviewActivity::where('subject_id', $doc->id)->where('action', 'processing needs attention')->exists())->toBeTrue();
});

it('reads xlsx metadata without calculating formulas and rejects oversized sparse sheets', function () {
    $book = new Spreadsheet;
    $book->getActiveSheet()->fromArray(RecordSpreadsheet::COLUMNS);
    $book->getActiveSheet()->fromArray(['Source', '', 'https://example.org/source', 'policy_report', '2026-11-01', 'Research Group', '', '=1+1'], null, 'A2');
    $path = tempnam(sys_get_temp_dir(), 'pls-xlsx-');
    try {
        (new Xlsx($book))->save($path);
        $rows = app(RecordSpreadsheet::class)->read($path, 'xlsx');
        expect($rows[0]['title'])->toBe('Source')->and($rows[0]['notes'])->toBe('=1+1');
        $book->getActiveSheet()->setCellValue('A500', 'Too many rows');
        (new Xlsx($book))->save($path);
        expect(fn () => app(RecordSpreadsheet::class)->read($path, 'xlsx'))->toThrow(ValidationException::class);
    } finally {
        unlink($path);
        $book->disconnectWorksheets();
    }
});

it('answers navigation questions and preserves safe markdown and clickable source titles', function () {
    $url = route('pls.reviews.documents.show', [$this->review, 123]);
    ReviewAssistantAgent::fake(["Open Reports and choose Generate report.\n\n## Why this helps\n\n- Your draft is saved.\n\n[Supplied source]($url)\n\n<script>alert(1)</script>"]);
    Livewire::test(AssistantSidebar::class, ['review' => $this->review, 'workspaceKey' => 'workflow'])
        ->call('sendPrompt', 'Where can I generate the findings report?')->assertHasNoErrors()
        ->assertSee('Open Reports and choose Generate report.')->assertSee('Details, sources and limitations')->assertSee('<h2>Why this helps</h2>', false)->assertSee('href="'.$url.'"', false)->assertDontSee('<script>alert(1)</script>', false);
    ReviewAssistantAgent::assertPrompted(fn () => true);
});

it('does not regrant removed access through a previously accepted invitation', function () {
    $person = User::factory()->create();
    $invite = $this->review->invitations()->create(['email' => $person->email, 'role' => 'viewer', 'invited_by' => auth()->id()]);
    $membership = $invite->acceptFor($person);
    $membership->delete();
    $this->actingAs($person)->get(route('pls.invitations.accept', $invite->token))->assertStatus(410);
    expect($this->review->memberships()->where('user_id', $person->id)->exists())->toBeFalse();
});

it('also protects edits through the report record modal from stale writes', function () {
    $report = nigeriaDraft($this->review);
    $editor = Livewire::test(ReportsPage::class, ['review' => $this->review])->call('startEditingReport', $report->id);
    $report->update(['content' => 'Newer editor text']);
    $editor->set('reportContent', 'Stale modal text')->call('updateReport')->assertHasErrors('reportContent');
    expect($report->fresh()->content)->toBe('Newer editor text');
});
