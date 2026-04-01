<?php

use App\Domain\Consultations\Consultation;
use App\Domain\Consultations\Enums\ConsultationType;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Stakeholders\Enums\StakeholderType;
use App\Domain\Stakeholders\Stakeholder;
use App\Livewire\Pls\Reviews\ConsultationsPage;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('consultations can be created from the review workspace', function () {
    $review = plsReview([
        'title' => 'Review of consultation planning and delivery',
    ]);

    $document = Document::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Public hearing agenda',
        'document_type' => DocumentType::GroupReport,
    ]);

    Livewire::test(ConsultationsPage::class, ['review' => $review])
        ->assertSee('Consultation activity')
        ->set('consultationTitle', 'Public hearing on implementation obstacles')
        ->set('consultationType', ConsultationType::Hearing->value)
        ->set('consultationHeldAt', '2026-03-11')
        ->set('consultationDocumentId', (string) $document->id)
        ->set('consultationSummary', 'Review-group members heard evidence on implementation delays and citizen access problems.')
        ->call('storeConsultation')
        ->assertHasNoErrors()
        ->assertSee('Public hearing on implementation obstacles');

    $this->assertDatabaseHas('consultations', [
        'pls_review_id' => $review->id,
        'title' => 'Public hearing on implementation obstacles',
        'consultation_type' => ConsultationType::Hearing->value,
        'document_id' => $document->id,
    ]);
});

test('consultations can be edited from the review workspace', function () {
    $review = plsReview([
        'title' => 'Review of public engagement records',
    ]);

    $consultation = Consultation::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Planned civil society roundtable',
        'consultation_type' => ConsultationType::Roundtable,
        'held_at' => null,
        'summary' => 'Initial planning session with civil society groups.',
        'document_id' => null,
    ]);

    Livewire::test(ConsultationsPage::class, ['review' => $review])
        ->call('startEditingConsultation', $consultation->id)
        ->set('consultationHeldAt', '2026-03-12')
        ->set('consultationSummary', 'Roundtable completed with agreement on evidence themes and outreach priorities.')
        ->call('updateConsultation')
        ->assertHasNoErrors()
        ->assertSee('Completed')
        ->assertSee('Mar 12, 2026');

    $this->assertDatabaseHas('consultations', [
        'id' => $consultation->id,
        'held_at' => '2026-03-12 00:00:00',
        'summary' => 'Roundtable completed with agreement on evidence themes and outreach priorities.',
    ]);
});

test('submissions can be logged from the review workspace', function () {
    $review = plsReview([
        'title' => 'Review of written evidence intake',
    ]);

    $stakeholder = Stakeholder::factory()->create([
        'pls_review_id' => $review->id,
        'name' => 'Belize Civil Society Forum',
        'stakeholder_type' => StakeholderType::Ngo,
    ]);

    $document = Document::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Civil society written submission',
        'document_type' => DocumentType::ConsultationSubmission,
    ]);

    Livewire::withQueryParams(['stakeholder' => $stakeholder->id])
        ->test(ConsultationsPage::class, ['review' => $review])
        ->assertSee('Written submissions')
        ->assertSet('submissionStakeholderId', (string) $stakeholder->id)
        ->set('submissionStakeholderId', (string) $stakeholder->id)
        ->set('submissionDocumentId', (string) $document->id)
        ->set('submissionSubmittedAt', '2026-03-09')
        ->set('submissionSummary', 'Requested stronger publication duties, compliance monitoring, and annual review-group review.')
        ->call('storeSubmission')
        ->assertHasNoErrors()
        ->assertSee('Belize Civil Society Forum')
        ->assertSee('Civil society written submission');

    $this->assertDatabaseHas('submissions', [
        'pls_review_id' => $review->id,
        'stakeholder_id' => $stakeholder->id,
        'document_id' => $document->id,
        'summary' => 'Requested stronger publication duties, compliance monitoring, and annual review-group review.',
    ]);
});

test('consultation and submission modals exclude legislation documents and clarify note fields', function () {
    $review = plsReview([
        'title' => 'Review of modal document options',
    ]);

    Stakeholder::factory()->create([
        'pls_review_id' => $review->id,
        'name' => 'Belize Chamber of Commerce',
        'stakeholder_type' => StakeholderType::IndustryGroup,
    ]);

    Document::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Primary Act source text',
        'document_type' => DocumentType::LegislationText,
    ]);

    Document::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Supporting consultation memo',
        'document_type' => DocumentType::GroupReport,
    ]);

    Livewire::test(ConsultationsPage::class, ['review' => $review])
        ->assertSee('Consultation note')
        ->assertSee('Review note')
        ->assertSeeHtml('wire:model.self="showAddConsultationModal"', false)
        ->assertSeeHtml('wire:model.self="showAddSubmissionModal"', false)
        ->assertSeeHtml('wire:model.live="submissionDocumentId"', false)
        ->assertSee('Supporting consultation memo')
        ->assertDontSee('Primary Act source text')
        ->assertDontSee('Legislation source files are excluded here.')
        ->assertDontSee('This can begin from the linked document summary, then be tailored to explain why the submission matters for this review.');
});

test('submission note prefills from the linked document summary without overwriting manual edits', function () {
    $review = plsReview([
        'title' => 'Review of submission note prefilling',
    ]);

    $stakeholder = Stakeholder::factory()->create([
        'pls_review_id' => $review->id,
        'name' => 'Belize Civil Society Forum',
        'stakeholder_type' => StakeholderType::Ngo,
    ]);

    $firstDocument = Document::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Civil society written submission',
        'document_type' => DocumentType::ConsultationSubmission,
        'summary' => 'Summarizes the submission file and its core concerns.',
    ]);

    $secondDocument = Document::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Follow-up submission',
        'document_type' => DocumentType::ConsultationSubmission,
        'summary' => 'Summarizes the follow-up file and its revised evidence.',
    ]);

    Livewire::withQueryParams(['stakeholder' => $stakeholder->id])
        ->test(ConsultationsPage::class, ['review' => $review])
        ->set('submissionDocumentId', (string) $firstDocument->id)
        ->assertSet('submissionSummary', 'Summarizes the submission file and its core concerns.')
        ->set('submissionSummary', 'Tailored review note explaining why this submission matters.')
        ->set('submissionDocumentId', (string) $secondDocument->id)
        ->assertSet('submissionSummary', 'Tailored review note explaining why this submission matters.');
});

test('consultations workspace removes dashboard cues and shows one consultation list', function () {
    $review = plsReview([
        'title' => 'Consultation workspace simplification',
    ]);

    $stakeholder = Stakeholder::factory()->create([
        'pls_review_id' => $review->id,
        'name' => 'Belize Chamber of Commerce',
        'stakeholder_type' => StakeholderType::IndustryGroup,
    ]);

    Consultation::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Implementation hearing',
        'consultation_type' => ConsultationType::Hearing,
        'held_at' => '2026-03-18',
        'summary' => 'Completed hearing about implementation barriers.',
    ]);

    Consultation::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Regional roundtable',
        'consultation_type' => ConsultationType::Roundtable,
        'held_at' => null,
        'summary' => 'Planned roundtable with regional stakeholders.',
    ]);

    Livewire::withQueryParams(['stakeholder' => $stakeholder->id])
        ->test(ConsultationsPage::class, ['review' => $review])
        ->assertSee('Consultation activity')
        ->assertSee('Written submissions')
        ->assertSee('Implementation hearing')
        ->assertSee('Regional roundtable')
        ->assertSee('Completed')
        ->assertSee('Planned')
        ->assertDontSee('Awaiting written evidence')
        ->assertDontSee('Submission handoff prepared for')
        ->assertDontSee('No completed consultation activity recorded yet.')
        ->assertDontSee('No planned consultation work queued yet.');
});
