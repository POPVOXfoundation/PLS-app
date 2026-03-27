<?php

use App\Domain\Consultations\Submission;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Stakeholders\Enums\ImplementingAgencyType;
use App\Domain\Stakeholders\Enums\StakeholderType;
use App\Domain\Stakeholders\ImplementingAgency;
use App\Domain\Stakeholders\Stakeholder;
use App\Livewire\Pls\Reviews\ConsultationsPage;
use App\Livewire\Pls\Reviews\StakeholdersPage;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('stakeholders can be added and filtered from the review workspace', function () {
    $review = plsReview([
        'title' => 'Review of consultation planning',
    ]);

    Stakeholder::factory()->create([
        'pls_review_id' => $review->id,
        'name' => 'Ministry of Finance',
        'stakeholder_type' => StakeholderType::Ministry,
    ]);

    Livewire::test(StakeholdersPage::class, ['review' => $review])
        ->set('stakeholderName', 'Open Budget Coalition')
        ->set('stakeholderType', StakeholderType::Ngo->value)
        ->set('stakeholderOrganization', 'Open Budget Coalition')
        ->set('stakeholderEmail', 'info@laravel.com')
        ->set('stakeholderPhone', '+1-202-555-0113')
        ->call('storeStakeholder')
        ->assertHasNoErrors()
        ->assertSee('Open Budget Coalition')
        ->set('stakeholderTypeFilter', StakeholderType::Ngo->value)
        ->assertSet('stakeholderTypeFilter', StakeholderType::Ngo->value)
        ->assertSee('Open Budget Coalition');

    $this->assertDatabaseHas('stakeholders', [
        'pls_review_id' => $review->id,
        'name' => 'Open Budget Coalition',
        'stakeholder_type' => StakeholderType::Ngo->value,
    ]);
});

test('stakeholders can be edited and submissions can be prepared from the stakeholder workspace', function () {
    $review = plsReview([
        'title' => 'Review of stakeholder coordination',
    ]);

    $stakeholder = Stakeholder::factory()->create([
        'pls_review_id' => $review->id,
        'name' => 'National Audit Office',
        'stakeholder_type' => StakeholderType::GovernmentAgency,
        'contact_details' => null,
    ]);

    Livewire::test(StakeholdersPage::class, ['review' => $review])
        ->assertSee('Stakeholder directory')
        ->assertSee('Missing contact detail')
        ->call('startEditingStakeholder', $stakeholder->id)
        ->set('stakeholderName', 'National Audit Office and Inspectorate')
        ->set('stakeholderType', StakeholderType::Expert->value)
        ->set('stakeholderOrganization', 'National Audit Office')
        ->set('stakeholderEmail', 'audit@laravel.com')
        ->call('updateStakeholder')
        ->assertHasNoErrors()
        ->assertSee('National Audit Office and Inspectorate')
        ->call('prepareSubmissionCreate', $stakeholder->id)
        ->assertRedirect(route('pls.reviews.consultations', ['review' => $review, 'stakeholder' => $stakeholder->id]));

    Livewire::withQueryParams(['stakeholder' => $stakeholder->id])
        ->test(ConsultationsPage::class, ['review' => $review])
        ->assertSet('submissionStakeholderId', (string) $stakeholder->id);

    $this->assertDatabaseHas('stakeholders', [
        'id' => $stakeholder->id,
        'name' => 'National Audit Office and Inspectorate',
        'stakeholder_type' => StakeholderType::Expert->value,
    ]);
});

test('stakeholder records show linked submissions in the review workspace', function () {
    $review = plsReview([
        'title' => 'Review of stakeholder evidence records',
    ]);

    $stakeholder = Stakeholder::factory()->create([
        'pls_review_id' => $review->id,
        'name' => 'National Access to Information Forum',
        'stakeholder_type' => StakeholderType::CitizenGroup,
    ]);

    $document = Document::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Forum written submission',
        'document_type' => DocumentType::ConsultationSubmission,
    ]);

    Submission::factory()->create([
        'pls_review_id' => $review->id,
        'stakeholder_id' => $stakeholder->id,
        'document_id' => $document->id,
        'summary' => 'Requested stronger publication deadlines and reporting transparency.',
    ]);

    Livewire::test(StakeholdersPage::class, ['review' => $review])
        ->assertSee('National Access to Information Forum')
        ->assertSee('Requested stronger publication deadlines and reporting transparency.')
        ->assertSee('Forum written submission');
});

test('implementing agencies can be added from the review workspace', function () {
    $review = plsReview([
        'title' => 'Review of implementation responsibility',
    ]);

    Livewire::test(StakeholdersPage::class, ['review' => $review])
        ->set('implementingAgencyName', 'Public Service Commission')
        ->set('implementingAgencyType', ImplementingAgencyType::Agency->value)
        ->call('storeImplementingAgency')
        ->assertHasNoErrors()
        ->assertSee('Public Service Commission');

    $this->assertDatabaseHas('implementing_agencies', [
        'pls_review_id' => $review->id,
        'name' => 'Public Service Commission',
        'agency_type' => ImplementingAgencyType::Agency->value,
    ]);

    expect(ImplementingAgency::query()->where('pls_review_id', $review->id)->count())->toBe(1);
});
