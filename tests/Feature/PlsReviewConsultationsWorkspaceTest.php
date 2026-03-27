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
        ->assertSee('Consultation and evidence intake')
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
        ->assertSee('Roundtable completed with agreement on evidence themes and outreach priorities.');

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
        ->assertSee('Submissions and evidence')
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
