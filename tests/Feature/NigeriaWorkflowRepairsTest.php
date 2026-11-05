<?php

use App\Ai\Agents\ReviewAssistantAgent;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Reporting\Actions\UpdateReport;
use App\Domain\Reporting\Report;
use App\Domain\Reviews\Enums\PlsReviewMembershipRole;
use App\Livewire\Pls\Reviews\AssistantSidebar;
use App\Livewire\Pls\Reviews\CollaboratorsPage;
use App\Livewire\Pls\Reviews\DocumentsPage;
use App\Livewire\Pls\Reviews\ReportsPage;
use App\Models\User;
use App\Notifications\ReviewAccessGrantedNotification;
use App\Support\PlsAssistant\ReviewAssistantGroundingRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravel\Ai\Prompts\AgentPrompt;
use Livewire\Livewire;

beforeEach(function () {
    Notification::fake();
    config()->set('mail.default', 'smtp');
    $this->actingAs(User::factory()->reviewer()->create());
});

test('report outlines retain all section text through save read and edit', function () {
    $review = plsReview();
    Livewire::test(ReportsPage::class, ['review' => $review])
        ->call('receiveReportOutline', "Report title: Nigeria review\nSECTION:\nTitle: Findings\nPurpose: Explain findings\nDraw on: Witness evidence\nLimitations: Missing regulations\nEND SECTION\nEND OUTLINE")
        ->call('prepareReportFromOutline')
        ->call('storeReport')->assertHasNoErrors();
    $report = $review->reports()->sole();
    expect($report->content)->toContain('Findings', 'Explain findings', 'Witness evidence', 'Missing regulations');
    Livewire::test(ReportsPage::class, ['review' => $review])
        ->call('viewReport', $report->id)->assertSee('Missing regulations')
        ->call('startEditingReport', $report->id)->assertSet('reportContent', $report->content)
        ->set('reportContent', 'Edited full report')->call('updateReport')->assertHasNoErrors();
    expect($report->fresh()->content)->toBe('Edited full report');
    Livewire::test(ReportsPage::class, ['review' => $review])
        ->call('viewReport', $report->id)->assertSee('Edited full report');
});

test('stale report editors cannot overwrite newer text and keep their unsaved work', function () {
    $review = plsReview();
    $report = Report::factory()->create(['pls_review_id' => $review->id, 'document_id' => null, 'content' => 'Original']);
    $first = Livewire::test(ReportsPage::class, ['review' => $review])->call('startEditingReport', $report->id);
    $second = Livewire::test(ReportsPage::class, ['review' => $review])->call('startEditingReport', $report->id);
    $first->set('reportContent', 'First saved')->call('updateReport')->assertHasNoErrors();
    $second->set('reportContent', 'Second unsaved')->call('updateReport')
        ->assertHasErrors('reportContent')->assertSet('reportContent', 'Second unsaved')->assertSet('showEditReportModal', true);
    expect($report->fresh()->content)->toBe('First saved');
});

test('metadata updates preserve report content and explicit empty text clears it', function () {
    $review = plsReview();
    $report = Report::factory()->create(['pls_review_id' => $review->id, 'document_id' => null, 'content' => 'Preserve me']);
    $input = ['report_id' => $report->id, 'pls_review_id' => $review->id, 'title' => 'Renamed', 'report_type' => $report->report_type->value, 'status' => $report->status->value];
    app(UpdateReport::class)->update($input);
    expect($report->fresh()->content)->toBe('Preserve me');
    app(UpdateReport::class)->update([...$input, 'content' => '']);
    expect($report->fresh()->content)->toBe('');
});

test('report readers enforce review access and escape report text', function () {
    $review = plsReview();
    $report = Report::factory()->create(['pls_review_id' => $review->id, 'document_id' => null, 'content' => '<script>alert(1)</script>']);
    $viewer = User::factory()->reviewer()->create();
    $review->memberships()->create(['user_id' => $viewer->id, 'role' => PlsReviewMembershipRole::Viewer]);
    $this->actingAs($viewer);
    Livewire::test(ReportsPage::class, ['review' => $review])->call('viewReport', $report->id)
        ->assertSeeHtml('&lt;script&gt;alert(1)&lt;/script&gt;')->assertDontSeeHtml('<script>alert(1)</script>')
        ->call('startEditingReport', $report->id)->assertForbidden();
    $this->actingAs(User::factory()->reviewer()->create());
    Livewire::test(ReportsPage::class, ['review' => $review])->assertForbidden();
});

test('legacy reports show their linked original and honest missing text message', function () {
    $review = plsReview();
    $document = Document::factory()->create(['pls_review_id' => $review->id]);
    $report = Report::factory()->create(['pls_review_id' => $review->id, 'document_id' => $document->id]);
    Livewire::test(ReportsPage::class, ['review' => $review])->call('viewReport', $report->id)
        ->assertSee('This report has no saved text.')->assertSeeHtml(route('pls.reviews.documents.original', [$review, $document]));
});

test('oversized report edits are rejected without losing the stored body', function () {
    $review = plsReview();
    $report = Report::factory()->create(['pls_review_id' => $review->id, 'document_id' => null, 'content' => 'Saved']);
    Livewire::test(ReportsPage::class, ['review' => $review])->call('startEditingReport', $report->id)
        ->set('reportContent', str_repeat('x', 200001))->call('updateReport')->assertHasErrors('reportContent');
    expect($report->fresh()->content)->toBe('Saved');
});

test('all legislation text is grounded in source order even beyond the evidence selection limit', function () {
    $review = plsReview();
    foreach (range(1, 4) as $number) {
        $document = Document::factory()->create(['pls_review_id' => $review->id, 'title' => "Law $number", 'document_type' => DocumentType::LegislationText]);
        $document->chunks()->create(['chunk_index' => 1, 'content' => "Late clause $number", 'token_count' => 5000]);
        $document->chunks()->create(['chunk_index' => 0, 'content' => "Early clause $number", 'token_count' => 5000]);
    }
    $review->load('documents.chunks');
    $references = app(ReviewAssistantGroundingRepository::class)->forPrompt($review, 'Explain duties', 'reports')['review'];
    expect($references)->toHaveCount(4);
    foreach ($references as $index => $reference) {
        $number = $index + 1;
        expect($reference['excerpt'])->toBe("Early clause $number\n\nLate clause $number");
    }
    expect(app(ReviewAssistantGroundingRepository::class)->forPrompt($review, 'Explain duties', 'documents')['review'])->toHaveCount(4);
    ReviewAssistantAgent::fake(['Grounded response']);
    Livewire::test(AssistantSidebar::class, ['review' => $review, 'workspaceKey' => 'reports'])
        ->call('sendPrompt', 'Summarize the legislation for the report')->assertSet('assistantError', null);
    ReviewAssistantAgent::assertPrompted(function (AgentPrompt $prompt): bool {
        $instructions = (string) $prompt->agent->instructions();
        foreach (range(1, 4) as $number) {
            if (! str_contains($instructions, "Early clause $number\n\nLate clause $number")) {
                return false;
            }
        }

        return true;
    });
});

test('original documents require review access and cannot cross reviews', function () {
    Storage::fake('local');
    $review = plsReview();
    Storage::disk('local')->put('review/evidence.html', '<script>bad()</script>');
    $document = Document::factory()->create(['pls_review_id' => $review->id, 'storage_path' => 'review/evidence.html', 'metadata' => ['disk' => 'local'], 'mime_type' => 'text/html']);
    $this->get(route('pls.reviews.documents.original', [$review, $document]))->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')->assertDownload();
    $other = plsReview();
    $this->get(route('pls.reviews.documents.original', [$other, $document]))->assertNotFound();
    $this->actingAs(User::factory()->reviewer()->create());
    $this->get(route('pls.reviews.documents.original', [$review, $document]))->assertForbidden();
});

test('document edits cannot redirect originals or spoof file response metadata', function () {
    $review = plsReview();
    $document = Document::factory()->create(['pls_review_id' => $review->id, 'document_type' => DocumentType::PolicyReport, 'storage_path' => 'review/owned.pdf', 'mime_type' => 'application/pdf', 'file_size' => 100]);
    Livewire::test(DocumentsPage::class, ['review' => $review])->call('startEditingDocument', $document->id)
        ->set('documentStoragePath', 'another-review/./secret.pdf')->set('documentMimeType', 'text/html')
        ->set('documentFileSize', '999')->set('documentTitle', 'Updated title')->call('saveDocumentEdits')->assertHasNoErrors();
    expect($document->fresh())->storage_path->toBe('review/owned.pdf')->mime_type->toBe('application/pdf')->file_size->toBe(100);
});

test('existing users are notified when access is granted regardless of email casing', function () {
    $review = plsReview();
    $invitee = User::factory()->reviewer()->create(['email' => 'Test.Invitee@example.com']);
    Livewire::test(CollaboratorsPage::class, ['review' => $review])->set('inviteCollaboratorEmail', 'TEST.INVITEE@example.com')
        ->call('shareReview')->assertHasNoErrors();
    expect($review->memberships()->where('user_id', $invitee->id)->exists())->toBeTrue();
    Notification::assertSentTo($invitee, ReviewAccessGrantedNotification::class, fn ($notification) => $notification->toMail($invitee)->actionUrl === route('pls.reviews.workflow', $review));
    expect($review->invitations()->count())->toBe(0);
});

test('logged out invitees return to acceptance after signing in', function () {
    $review = plsReview();
    $invitee = User::factory()->reviewer()->create();
    $invitation = $review->invitations()->create(['email' => $invitee->email, 'role' => PlsReviewMembershipRole::Viewer, 'invited_by' => auth()->id()]);
    auth()->logout();
    $url = route('pls.invitations.accept', $invitation->token);
    $this->get($url)->assertRedirect(route('login'))->assertSessionHas('url.intended', $url);
    $this->post(route('login'), ['email' => $invitee->email, 'password' => 'password'])->assertRedirect($url);
    $this->get($url)->assertRedirect(route('pls.reviews.workflow', $review));
    expect($invitation->fresh()->isAccepted())->toBeTrue();
});

test('expired invitations cannot grant access and no longer block a fresh invitation', function () {
    $review = plsReview();
    $invitee = User::factory()->reviewer()->create();
    $invitation = $review->invitations()->create(['email' => $invitee->email, 'role' => PlsReviewMembershipRole::Contributor, 'invited_by' => auth()->id(), 'expires_at' => now()->subDay()]);
    expect($invitation->isPending())->toBeFalse();
    expect($review->pendingInvitations()->exists())->toBeFalse();
    $this->actingAs($invitee)->get(route('pls.invitations.accept', $invitation->token))->assertGone();
    expect($review->memberships()->where('user_id', $invitee->id)->exists())->toBeFalse();
});

test('report readers cannot open a report belonging to another review', function () {
    $review = plsReview();
    $foreign = Report::factory()->create();
    $this->expectException(ModelNotFoundException::class);
    Livewire::test(ReportsPage::class, ['review' => $review])->call('viewReport', $foreign->id);
});

test('oversized legislation fails visibly before an AI call without silently dropping sources', function () {
    $review = plsReview();
    config()->set('pls_assistant.max_context_characters', 1000);
    $document = Document::factory()->create(['pls_review_id' => $review->id, 'document_type' => DocumentType::LegislationText]);
    $document->chunks()->create(['chunk_index' => 0, 'content' => str_repeat('Law clause. ', 1000), 'token_count' => 3000]);
    ReviewAssistantAgent::fake();
    Livewire::test(AssistantSidebar::class, ['review' => $review, 'workspaceKey' => 'reports'])
        ->call('sendPrompt', 'Prepare an outline', false, true)
        ->assertSet('assistantInput', 'Prepare an outline')
        ->assertSee('No AI request was sent; legislation was not silently shortened.')
        ->assertDispatchedTo(ReportsPage::class, 'report-outline-failed');
    ReviewAssistantAgent::assertNeverPrompted();
});

test('accepting an invitation for an existing collaborator consumes it without changing their role', function () {
    $review = plsReview();
    $invitee = User::factory()->reviewer()->create();
    $review->memberships()->create(['user_id' => $invitee->id, 'role' => PlsReviewMembershipRole::Viewer]);
    $invitation = $review->invitations()->create(['email' => $invitee->email, 'role' => PlsReviewMembershipRole::Contributor, 'invited_by' => auth()->id()]);
    $this->actingAs($invitee)->get(route('pls.invitations.accept', $invitation->token))->assertRedirect();
    expect($invitation->fresh()->isAccepted())->toBeTrue();
    expect($review->memberships()->where('user_id', $invitee->id)->sole()->role)->toBe(PlsReviewMembershipRole::Viewer);
    $this->get(route('pls.invitations.accept', $invitation->token))->assertNotFound();
});

test('unparseable outline responses save the displayed fallback rather than unrelated assistant text', function () {
    $review = plsReview();
    Livewire::test(ReportsPage::class, ['review' => $review])->call('receiveReportOutline', 'Cannot draft this response')
        ->call('prepareReportFromOutline')->call('storeReport')->assertHasNoErrors();
    expect($review->reports()->sole()->content)->toContain('Limitations:')->not->toContain('Cannot draft this response');
});
