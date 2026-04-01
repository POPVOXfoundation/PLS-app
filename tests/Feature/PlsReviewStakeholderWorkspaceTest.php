<?php

use App\Domain\Stakeholders\Enums\ImplementingAgencyType;
use App\Domain\Stakeholders\Enums\StakeholderType;
use App\Domain\Stakeholders\Stakeholder;
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
        ->assertSeeHtml('wire:model.self="showAddStakeholderModal"', false)
        ->assertSeeHtml('wire:model.self="showEditStakeholderModal"', false)
        ->assertSeeHtml('wire:model.self="showAddImplementingAgencyModal"', false)
        ->call('prepareStakeholderCreate')
        ->assertSet('showAddStakeholderModal', true)
        ->set('stakeholderName', 'Open Budget Coalition')
        ->set('stakeholderType', StakeholderType::Ngo->value)
        ->set('stakeholderOrganization', 'Open Budget Coalition')
        ->set('stakeholderEmail', 'info@laravel.com')
        ->set('stakeholderPhone', '+1-202-555-0113')
        ->call('storeStakeholder')
        ->assertSet('showAddStakeholderModal', false)
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

test('stakeholders can be edited from the stakeholder workspace', function () {
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
        ->call('startEditingStakeholder', $stakeholder->id)
        ->assertSet('showEditStakeholderModal', true)
        ->set('stakeholderName', 'National Audit Office and Inspectorate')
        ->set('stakeholderType', StakeholderType::Expert->value)
        ->set('stakeholderOrganization', 'National Audit Office')
        ->set('stakeholderEmail', 'audit@laravel.com')
        ->call('updateStakeholder')
        ->assertSet('showEditStakeholderModal', false)
        ->assertHasNoErrors()
        ->assertSee('National Audit Office and Inspectorate');

    $this->assertDatabaseHas('stakeholders', [
        'id' => $stakeholder->id,
        'name' => 'National Audit Office and Inspectorate',
        'stakeholder_type' => StakeholderType::Expert->value,
    ]);
});

test('implementing agencies can be added from the review workspace', function () {
    $review = plsReview([
        'title' => 'Review of implementation responsibility',
    ]);

    Livewire::test(StakeholdersPage::class, ['review' => $review])
        ->call('prepareImplementingAgencyCreate')
        ->assertSet('showAddImplementingAgencyModal', true)
        ->set('implementingAgencyName', 'Public Service Commission')
        ->set('implementingAgencyType', ImplementingAgencyType::Agency->value)
        ->call('storeImplementingAgency')
        ->assertSet('showAddImplementingAgencyModal', false)
        ->assertHasNoErrors()
        ->assertSee('Public Service Commission');

    $this->assertDatabaseHas('implementing_agencies', [
        'pls_review_id' => $review->id,
        'name' => 'Public Service Commission',
        'agency_type' => ImplementingAgencyType::Agency->value,
    ]);
});

test('simplified page does not render removed elements', function () {
    $review = plsReview();

    Stakeholder::factory()->create([
        'pls_review_id' => $review->id,
        'name' => 'Test Stakeholder',
        'stakeholder_type' => StakeholderType::Ministry,
        'contact_details' => null,
    ]);

    Livewire::test(StakeholdersPage::class, ['review' => $review])
        ->assertSee('Stakeholder directory')
        ->assertDontSee('Stakeholders mapped')
        ->assertDontSee('With submissions')
        ->assertDontSee('Review-team cues')
        ->assertDontSee('Awaiting written evidence')
        ->assertDontSee('Add submission')
        ->assertDontSee('Evidence received')
        ->assertDontSee('Awaiting evidence');
});
