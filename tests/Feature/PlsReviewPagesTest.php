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
use App\Domain\Reviews\Enums\PlsReviewMembershipRole;
use App\Domain\Reviews\PlsReview;
use App\Livewire\Pls\Reviews\Create as CreateReviewPage;
use App\Livewire\Pls\Reviews\WorkflowPage;
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
        ->assertSee($review->assignmentLabel());
});

test('review can be created from the create page without a review group', function () {
    ['legislature' => $legislature] = plsHierarchy();

    $response = Livewire::test(CreateReviewPage::class)
        ->set('legislature_id', (string) $legislature->id)
        ->set('title', 'Post-Legislative Review of the Public Procurement Act')
        ->set('description', 'Evaluates implementation delays, supplier access, and compliance monitoring.')
        ->set('start_date', '2026-03-10')
        ->call('save')
        ->assertHasNoErrors();

    $review = PlsReview::query()
        ->where('title', 'Post-Legislative Review of the Public Procurement Act')
        ->firstOrFail();

    expect($review->review_group_id)->toBeNull();
    expect($review->created_by)->toBe(auth()->id());
    expect($review->legislature_id)->toBe($legislature->id);
    expect($review->jurisdiction_id)->toBe($legislature->jurisdiction_id);
    expect($review->country_id)->toBe($legislature->jurisdiction->country_id);
    expect($review->steps()->count())->toBe(11);
    expect($review->memberships()->where('user_id', auth()->id())->firstOrFail()->role)->toBe(PlsReviewMembershipRole::Owner);
    $response->assertRedirect(route('pls.reviews.workflow', ['review' => $review->id]));
});

test('review can be created from the create page with a review group', function () {
    ['legislature' => $legislature, 'reviewGroup' => $reviewGroup] = plsHierarchy();

    Livewire::test(CreateReviewPage::class)
        ->set('legislature_id', (string) $legislature->id)
        ->set('review_group_id', (string) $reviewGroup->id)
        ->set('title', 'Review group scoped review')
        ->set('description', 'Evaluates implementation under a designated review group.')
        ->call('save')
        ->assertHasNoErrors();

    $review = PlsReview::query()
        ->where('title', 'Review group scoped review')
        ->firstOrFail();

    expect($review->review_group_id)->toBe($reviewGroup->id)
        ->and($review->created_by)->toBe(auth()->id())
        ->and($review->memberships()->where('user_id', auth()->id())->firstOrFail()->role)->toBe(PlsReviewMembershipRole::Owner);
});

test('review create page validates required fields', function () {
    $component = Livewire::test(CreateReviewPage::class)
        ->call('save')
        ->assertHasErrors([
            'legislature_id' => ['required'],
            'title' => ['required'],
        ]);

    expect(substr_count($component->html(), 'Choose the legislature for this review.'))->toBe(1);
    expect(substr_count($component->html(), 'Enter the public-facing review title.'))->toBe(1);
});

test('review create page does not render the seeded workflow panel', function () {
    $this->get(route('pls.reviews.create'))
        ->assertOk()
        ->assertSee('Institution preview')
        ->assertDontSee('Seeded workflow')
        ->assertDontSee('Every new review starts with the canonical 11-step post-legislative scrutiny workflow.');
});

test('review show route redirects to workflow route', function () {
    $review = plsReview();

    $this->get(route('pls.reviews.show', ['review' => $review->id]))
        ->assertRedirect(route('pls.reviews.workflow', ['review' => $review->id]));
});

test('review workflow page renders workflow details and supports step switching', function () {
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

    $response = $this->get(route('pls.reviews.workflow', ['review' => $review->id]));

    $response
        ->assertOk()
        ->assertSee(config('app.name'))
        ->assertSee($review->title)
        ->assertSee('Collaborators')
        ->assertSee('PLS Assistant')
        ->assertSee('Define the objectives and scope of PLS')
        ->assertSee('Analyse post-legislative scrutiny findings')
        ->assertSee('dark:bg-white', false)
        ->assertSee('bg-violet-50', false)
        ->assertSee('text-violet-900', false)
        ->assertSee('bg-violet-50 font-semibold text-violet-900', false)
        ->assertDontSee('animate-in fade-in duration-150', false)
        ->assertDontSee('dark:bg-violet-950/50', false)
        ->assertDontSee('dark:hover:border-zinc-700 dark:hover:bg-zinc-800 dark:hover:text-zinc-200', false)
        ->assertDontSee('dark:bg-zinc-800 dark:text-zinc-100', false)
        ->assertDontSee('dark:bg-zinc-800/80', false)
        ->assertDontSee('dark:hover:bg-zinc-900', false)
        ->assertDontSee('dark:bg-violet-950/30', false)
        ->assertDontSee('dark:bg-violet-900"', false);

    $component = Livewire::test(WorkflowPage::class, ['review' => $review])
        ->assertSee('Define the objectives and scope of PLS')
        ->assertSee('Analyse post-legislative scrutiny findings')
        ->assertSee('Synthesize evidence into findings and identify the strongest recommendation themes.')
        ->assertSee('Current');

    $html = $component->html();

    expect($html)->not->toContain('Best next area')
        ->and($html)->not->toContain('Do next')
        ->and($html)->not->toContain('Step 1 of 11')
        ->and($html)->not->toContain('data-flux-accordion-item')
        ->and($html)->not->toContain('Legislation linked')
        ->and($html)->not->toContain('No notes recorded.')
        ->and($html)->not->toContain('wire:click="selectStep(');
});

test('all review section routes render inside the shared workspace shell', function () {
    $review = plsReview([
        'title' => 'Route-based review workspace',
    ]);

    $routes = [
        'pls.reviews.workflow' => 'Define the objectives and scope of PLS',
        'pls.reviews.collaborators' => 'Collaborators',
        'pls.reviews.legislation' => 'No records saved for this review yet.',
        'pls.reviews.documents' => 'Document file',
        'pls.reviews.stakeholders' => 'Stakeholder directory',
        'pls.reviews.consultations' => 'Consultation activity',
        'pls.reviews.analysis' => 'Findings & recommendations',
        'pls.reviews.reports' => 'Reporting workspace',
    ];

    foreach ($routes as $route => $expectedText) {
        $this->get(route($route, ['review' => $review->id]))
            ->assertOk()
            ->assertSee($review->title)
            ->assertSee('PLS Assistant')
            ->assertSee('Workflow')
            ->assertSee($expectedText)
            ->assertSee('All reviews')
            ->assertDontSee('New review');
    }
});

test('legislation page uses inline source analysis instead of attach and create modals', function () {
    $review = plsReview([
        'title' => 'Inline legislation flow',
    ]);

    $this->get(route('pls.reviews.legislation', ['review' => $review->id]))
        ->assertOk()
        ->assertSee('Source file')
        ->assertSee('Add the source text and it will appear in the records table below.')
        ->assertSee('50 MB max')
        ->assertSee('Records')
        ->assertDontSee('Attach legislation')
        ->assertDontSee('Create legislation');
});

test('documents page uses inline upload analysis instead of metadata-first add and edit modals', function () {
    $review = plsReview([
        'title' => 'Inline documents flow',
    ]);

    $this->get(route('pls.reviews.documents', ['review' => $review->id]))
        ->assertOk()
        ->assertSee('Document file')
        ->assertSee('Choose files')
        ->assertSee('Records')
        ->assertDontSee('Storage path')
        ->assertDontSee('File size (bytes)');
});

test('consultations page does not render the intake summary box', function () {
    $review = plsReview([
        'title' => 'Consultations workspace',
    ]);

    $this->get(route('pls.reviews.consultations', ['review' => $review->id]))
        ->assertOk()
        ->assertSee('Consultation activity')
        ->assertSee('Submissions and evidence')
        ->assertDontSee('Consultation and evidence intake')
        ->assertDontSee('Keep planned engagement, completed activity, and written evidence in one workspace so the review team can trace participation back to the workflow.');
});
