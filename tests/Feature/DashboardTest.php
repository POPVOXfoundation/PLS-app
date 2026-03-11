<?php

use App\Domain\Reviews\Actions\CompletePlsReviewStep;
use App\Domain\Reviews\Actions\CreatePlsReview;
use App\Domain\Reviews\Actions\StartPlsReviewStep;
use App\Domain\Reviews\Data\CreatePlsReviewData;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('New review')
        ->assertSee('All reviews');
});

test('dashboard shows portfolio metrics, review activity, and attention reviews', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $firstContext = plsReviewContext([
        'title' => 'Review of procurement implementation',
    ], [
        'committee' => [
            'name' => 'Public Accounts Committee',
            'slug' => 'public-accounts-committee',
        ],
    ]);

    app(CreatePlsReview::class)->create(new CreatePlsReviewData(
        committeeId: $firstContext['committee']->id,
        title: 'Review of delegated procurement regulations',
        description: 'Examines delegated legislation and oversight follow-up.',
        startDate: \Carbon\CarbonImmutable::parse('2026-03-10'),
    ));

    $thirdContext = plsReviewContext([
        'title' => 'Review of access to information implementation',
    ], [
        'committee' => [
            'name' => 'Governance and Legal Affairs Committee',
            'slug' => 'governance-and-legal-affairs-committee',
        ],
    ]);

    $activeReview = app(StartPlsReviewStep::class)->start(
        $firstContext['review']->steps()->where('step_number', 1)->firstOrFail(),
    );

    $completedReview = $thirdContext['review'];

    foreach (range(1, 11) as $stepNumber) {
        $completedReview = app(CompletePlsReviewStep::class)->complete(
            $completedReview->steps()->where('step_number', $stepNumber)->firstOrFail(),
        );
    }

    $response = $this->get(route('dashboard'));

    $response
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('Reviews')
        ->assertSee('Active')
        ->assertSee('Needs attention')
        ->assertSee('Committee workload')
        ->assertSee('Review of procurement implementation')
        ->assertSee('Review of delegated procurement regulations')
        ->assertSee('Public Accounts Committee')
        ->assertSee('Governance and Legal Affairs Committee');
});
