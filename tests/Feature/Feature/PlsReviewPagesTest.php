<?php

use App\Domain\Analysis\Enums\FindingType;
use App\Domain\Analysis\Enums\RecommendationType;
use App\Domain\Analysis\Finding;
use App\Domain\Analysis\Recommendation;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Legislation\Enums\LegislationType;
use App\Domain\Legislation\Enums\ReviewLegislationRelationshipType;
use App\Domain\Legislation\Legislation;
use App\Domain\Reviews\PlsReview;
use App\Livewire\Pls\Reviews\Create as CreateReviewPage;
use App\Livewire\Pls\Reviews\Show as ShowReviewPage;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

test('reviews index page is displayed', function () {
    $review = plsReview();

    $response = $this->get(route('pls.reviews.index'));

    $response
        ->assertOk()
        ->assertSee('PLS Reviews')
        ->assertSee($review->title)
        ->assertSee($review->committee->name);
});

test('review can be created from the create page', function () {
    $committee = plsHierarchy()['committee'];

    $response = Livewire::test(CreateReviewPage::class)
        ->set('committee_id', (string) $committee->id)
        ->set('title', 'Post-Legislative Review of the Public Procurement Act')
        ->set('description', 'Evaluates implementation delays, supplier access, and compliance monitoring.')
        ->set('start_date', '2026-03-10')
        ->call('save')
        ->assertHasNoErrors();

    $review = PlsReview::query()
        ->where('title', 'Post-Legislative Review of the Public Procurement Act')
        ->firstOrFail();

    expect($review->committee_id)->toBe($committee->id);
    expect($review->legislature_id)->toBe($committee->legislature_id);
    expect($review->jurisdiction_id)->toBe($committee->legislature->jurisdiction_id);
    expect($review->country_id)->toBe($committee->legislature->jurisdiction->country_id);
    expect($review->steps()->count())->toBe(11);
    $response->assertRedirect(route('pls.reviews.show', ['review' => $review->id]));
});

test('review create page validates required fields', function () {
    $component = Livewire::test(CreateReviewPage::class)
        ->call('save')
        ->assertHasErrors([
            'committee_id' => ['required'],
            'title' => ['required'],
        ]);

    expect(substr_count($component->html(), 'Choose the committee responsible for this review.'))->toBe(1);
    expect(substr_count($component->html(), 'Enter the public-facing review title.'))->toBe(1);
});

test('review show page renders workflow details and supports step switching', function () {
    $review = plsReview([
        'title' => 'Review of the Access to Information Act',
        'description' => 'Focuses on response timelines, disclosure quality, and agency compliance.',
    ]);

    $legislation = Legislation::factory()->create([
        'jurisdiction_id' => $review->jurisdiction_id,
        'title' => 'Access to Information Act',
        'short_title' => 'ATI Act',
        'legislation_type' => LegislationType::Act,
        'summary' => 'Establishes rights of access to public information and baseline disclosure duties.',
    ]);

    $review->legislation()->attach($legislation->id, [
        'relationship_type' => ReviewLegislationRelationshipType::Primary,
    ]);

    $document = Document::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Implementation Progress Report',
        'document_type' => DocumentType::ImplementationReport,
        'summary' => 'Summarizes implementation bottlenecks across line ministries.',
    ]);

    $finding = Finding::factory()->create([
        'pls_review_id' => $review->id,
        'title' => 'Proactive disclosure remains uneven across ministries',
        'finding_type' => FindingType::ImplementationGap,
        'summary' => 'Several public authorities are not publishing required information consistently.',
    ]);

    Recommendation::factory()->create([
        'pls_review_id' => $review->id,
        'finding_id' => $finding->id,
        'title' => 'Issue a standard disclosure directive',
        'description' => 'Adopt publication templates and quarterly compliance tracking for all ministries.',
        'recommendation_type' => RecommendationType::ImproveImplementation,
    ]);

    $response = $this->get(route('pls.reviews.show', ['review' => $review->id]));

    $response
        ->assertOk()
        ->assertSee($review->title)
        ->assertSee($legislation->title)
        ->assertSee($document->title)
        ->assertSee($finding->title);

    Livewire::test(ShowReviewPage::class, ['review' => $review])
        ->assertSet('selectedStepNumber', 1)
        ->call('selectStep', 6)
        ->assertSet('selectedStepNumber', 6)
        ->assertSee('Analyse post-legislative scrutiny findings')
        ->assertSee('Issue a standard disclosure directive');
});
