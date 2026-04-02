<?php

use App\Domain\Analysis\Enums\FindingType;
use App\Domain\Analysis\Enums\RecommendationType;
use App\Domain\Analysis\Finding;
use App\Domain\Analysis\Recommendation;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Institutions\Country;
use App\Domain\Institutions\Enums\JurisdictionType;
use App\Domain\Legislation\Enums\LegislationType;
use App\Domain\Legislation\Enums\ReviewLegislationRelationshipType;
use App\Domain\Legislation\Legislation;
use App\Domain\Reviews\Enums\PlsReviewMembershipRole;
use App\Domain\Reviews\PlsReview;
use App\Livewire\Pls\Reviews\Create as CreateReviewPage;
use App\Livewire\Pls\Reviews\WorkflowPage;
use App\Models\User;
use App\Support\Toast;
use Livewire\Livewire;

beforeEach(function () {
    $country = Country::factory()->create();

    $this->actingAs(User::factory()->create([
        'country_id' => $country->id,
    ]));
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

test('review can be created from the create page with a selected inquiry lead', function () {
    ['country' => $country, 'jurisdiction' => $jurisdiction, 'legislature' => $legislature, 'reviewGroup' => $reviewGroup] = plsHierarchy();
    $user = User::factory()->reviewer()->create([
        'country_id' => $country->id,
    ]);

    $response = Livewire::actingAs($user)
        ->test(CreateReviewPage::class)
        ->set('scope', 'national')
        ->set('legislature_id', (string) $legislature->id)
        ->set('review_group_id', (string) $reviewGroup->id)
        ->set('title', 'Post-Legislative Review of the Public Procurement Act')
        ->set('description', 'Evaluates implementation delays, supplier access, and compliance monitoring.')
        ->set('start_date', '2026-03-10')
        ->call('save')
        ->assertHasNoErrors();

    $review = PlsReview::query()
        ->where('title', 'Post-Legislative Review of the Public Procurement Act')
        ->firstOrFail();

    expect($review->review_group_id)->toBe($reviewGroup->id)
        ->and($review->created_by)->toBe($user->id)
        ->and($review->legislature_id)->toBe($legislature->id)
        ->and($review->jurisdiction_id)->toBe($jurisdiction->id)
        ->and($review->country_id)->toBe($legislature->jurisdiction->country_id)
        ->and($review->steps()->count())->toBe(11)
        ->and($review->memberships()->where('user_id', $user->id)->firstOrFail()->role)->toBe(PlsReviewMembershipRole::Owner);
    expect(session()->get('toast'))->toBe(Toast::success('Review created', 'Your review is ready.'));
    $response->assertRedirect(route('pls.reviews.workflow', ['review' => $review->id]));
});

test('review can be created from the create page without an inquiry lead', function () {
    ['country' => $country, 'jurisdiction' => $jurisdiction, 'legislature' => $legislature] = plsHierarchy();
    $user = User::factory()->reviewer()->create([
        'country_id' => $country->id,
    ]);

    $response = Livewire::actingAs($user)
        ->test(CreateReviewPage::class)
        ->set('scope', 'national')
        ->set('legislature_id', (string) $legislature->id)
        ->set('title', 'Review without named inquiry lead')
        ->set('description', 'Confirms inquiry lead stays optional on the create form.')
        ->set('start_date', '2026-03-10')
        ->call('save')
        ->assertHasNoErrors();

    $review = PlsReview::query()
        ->where('title', 'Review without named inquiry lead')
        ->firstOrFail();

    expect($review->review_group_id)->toBeNull()
        ->and($review->created_by)->toBe($user->id)
        ->and($review->legislature_id)->toBe($legislature->id)
        ->and($review->jurisdiction_id)->toBe($jurisdiction->id)
        ->and($review->country_id)->toBe($legislature->jurisdiction->country_id);
    $response->assertRedirect(route('pls.reviews.workflow', ['review' => $review->id]));
});

test('review can create a sub-national jurisdiction, legislature, and inquiry lead inline', function () {
    ['country' => $country, 'jurisdiction' => $nationalJurisdiction] = plsHierarchy();
    $user = User::factory()->reviewer()->create([
        'country_id' => $country->id,
    ]);

    Livewire::actingAs($user)
        ->test(CreateReviewPage::class)
        ->set('scope', 'subnational')
        ->set('jurisdiction_search', 'Tennessee')
        ->call('createJurisdiction')
        ->set('legislature_search', 'Tennessee General Assembly')
        ->call('createLegislature')
        ->set('review_group_search', 'State and Local Government Office')
        ->call('createReviewGroup')
        ->set('title', 'State-level scrutiny review')
        ->set('description', 'Evaluates implementation under a newly created state legislature and inquiry lead.')
        ->call('save')
        ->assertHasNoErrors();

    $review = PlsReview::query()
        ->where('title', 'State-level scrutiny review')
        ->firstOrFail();
    $jurisdiction = $review->jurisdiction()->firstOrFail();
    $legislature = $review->legislature()->firstOrFail();
    $reviewGroup = $review->reviewGroup()->firstOrFail();

    expect($review->created_by)->toBe($user->id)
        ->and($review->country_id)->toBe($country->id)
        ->and($jurisdiction->name)->toBe('Tennessee')
        ->and($jurisdiction->jurisdiction_type)->toBe(JurisdictionType::Region)
        ->and($jurisdiction->parent_id)->toBe($nationalJurisdiction->id)
        ->and($legislature->name)->toBe('Tennessee General Assembly')
        ->and($legislature->jurisdiction_id)->toBe($jurisdiction->id)
        ->and($reviewGroup->name)->toBe('State and Local Government Office')
        ->and($reviewGroup->country_id)->toBe($country->id)
        ->and($reviewGroup->jurisdiction_id)->toBe($jurisdiction->id)
        ->and($reviewGroup->legislature_id)->toBe($legislature->id);
});

test('review create page validates the national path required fields', function () {
    $component = Livewire::test(CreateReviewPage::class)
        ->set('scope', 'national')
        ->call('save')
        ->assertHasErrors([
            'legislature_id' => ['required'],
            'title' => ['required'],
            'description' => ['required'],
        ]);

    expect(substr_count($component->html(), 'Choose the legislature for this review.'))->toBe(1);
    expect(substr_count($component->html(), 'Enter a title for this review.'))->toBe(1);
    expect(substr_count($component->html(), 'Enter a working summary for this review.'))->toBe(1);
});

test('review create page requires sub-national jurisdiction details when that scope is selected', function () {
    Livewire::test(CreateReviewPage::class)
        ->set('scope', 'subnational')
        ->call('save')
        ->assertHasErrors([
            'jurisdiction_id' => ['required'],
            'legislature_id' => ['required'],
            'title' => ['required'],
            'description' => ['required'],
        ]);
});

test('review create page does not ask for country again and uses inquiry lead copy', function () {
    $this->get(route('pls.reviews.create'))
        ->assertOk()
        ->assertSee('National')
        ->assertSee('Sub-national')
        ->assertDontSee('Review group')
        ->assertDontSee('Select a country')
        ->assertDontSee('Country scope');
});

test('review create preview does not duplicate national scope as a jurisdiction row', function () {
    Livewire::test(CreateReviewPage::class)
        ->set('scope', 'national')
        ->assertDontSee('Jurisdiction');
});

test('review create page prevents cross-country creation', function () {
    ['jurisdiction' => $jurisdiction, 'legislature' => $legislature, 'reviewGroup' => $reviewGroup] = plsHierarchy();
    $userCountry = Country::factory()->create();
    $user = User::factory()->reviewer()->create([
        'country_id' => $userCountry->id,
    ]);

    Livewire::actingAs($user)
        ->test(CreateReviewPage::class)
        ->set('scope', 'national')
        ->set('legislature_id', (string) $legislature->id)
        ->set('review_group_id', (string) $reviewGroup->id)
        ->set('title', 'Blocked cross-country review')
        ->set('description', 'Cross-country creation should fail even when the rest of the form is filled in.')
        ->call('save')
        ->assertHasErrors('legislature_id');
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
        'pls.reviews.reports' => 'Government responses',
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
        ->assertSee('Written submissions')
        ->assertDontSee('Consultation and evidence intake')
        ->assertDontSee('Keep planned engagement, completed activity, and written evidence in one workspace so the review team can trace participation back to the workflow.');
});

test('consultations page hides removed dashboard cues', function () {
    $review = plsReview([
        'title' => 'Consultations workspace simplification',
    ]);

    $this->get(route('pls.reviews.consultations', ['review' => $review->id]))
        ->assertOk()
        ->assertDontSee('Awaiting written evidence')
        ->assertDontSee('Records 0')
        ->assertDontSee('No completed consultation activity recorded yet.')
        ->assertDontSee('No planned consultation work queued yet.')
        ->assertDontSee('Submission handoff prepared for');
});
