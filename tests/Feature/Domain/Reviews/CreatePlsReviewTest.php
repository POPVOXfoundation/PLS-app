<?php

use App\Domain\Institutions\Committee;
use App\Domain\Reviews\Actions\CreatePlsReview;
use App\Domain\Reviews\Data\CreatePlsReviewData;
use App\Domain\Reviews\Enums\PlsReviewStatus;
use App\Domain\Reviews\Support\PlsReviewWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

it('creates a draft review with the derived institutional hierarchy', function () {
    ['country' => $country, 'jurisdiction' => $jurisdiction, 'legislature' => $legislature, 'committee' => $committee] = plsHierarchy([
        'country' => [
            'name' => 'Belize',
            'iso2' => 'BZ',
            'iso3' => 'BLZ',
        ],
        'jurisdiction' => [
            'name' => 'National',
            'slug' => 'national',
        ],
        'legislature' => [
            'name' => 'National Assembly',
            'slug' => 'national-assembly',
        ],
        'committee' => [
            'name' => 'Governance and Oversight Committee',
            'slug' => 'governance-and-oversight-committee',
        ],
    ]);

    $review = app(CreatePlsReview::class)->create(
        CreatePlsReviewData::from([
            'committee_id' => $committee->id,
            'title' => 'Review of the Access to Information Act',
            'description' => 'Assess implementation outcomes after enactment.',
            'start_date' => '2026-03-10',
        ]),
    );

    expect($review->status)->toBe(PlsReviewStatus::Draft)
        ->and($review->current_step_number)->toBe(1)
        ->and($review->committee_id)->toBe($committee->id)
        ->and($review->legislature_id)->toBe($legislature->id)
        ->and($review->jurisdiction_id)->toBe($jurisdiction->id)
        ->and($review->country_id)->toBe($country->id)
        ->and($review->slug)->toBe('review-of-the-access-to-information-act')
        ->and($review->start_date?->toDateString())->toBe('2026-03-10');

    $this->assertDatabaseHas('pls_reviews', [
        'id' => $review->id,
        'committee_id' => $committee->id,
        'legislature_id' => $legislature->id,
        'jurisdiction_id' => $jurisdiction->id,
        'country_id' => $country->id,
        'status' => PlsReviewStatus::Draft->value,
        'current_step_number' => 1,
    ]);
});

it('seeds the official workflow steps in build plan order', function () {
    $committee = plsHierarchy()['committee'];

    $review = app(CreatePlsReview::class)->create(new CreatePlsReviewData(
        committeeId: $committee->id,
        title: 'Review of delegated legislation oversight',
        description: null,
        startDate: CarbonImmutable::parse('2026-03-11'),
    ));

    $steps = $review->steps()->get();
    $expectedDefinitions = PlsReviewWorkflow::definitions();

    expect($steps)->toHaveCount(11)
        ->and($steps->pluck('step_number')->all())->toBe(array_column($expectedDefinitions, 'number'))
        ->and($steps->pluck('step_key')->all())->toBe(array_column($expectedDefinitions, 'key'))
        ->and($steps->map(fn ($step) => $step->title)->all())->toBe(array_column($expectedDefinitions, 'title'))
        ->and($steps->pluck('status')->map(fn ($status) => $status->value)->all())->toBe(array_fill(0, 11, 'pending'));
});

it('creates unique slugs for reviews with the same title in the same committee', function () {
    $committee = plsHierarchy()['committee'];

    $action = app(CreatePlsReview::class);

    $firstReview = $action->create(new CreatePlsReviewData(
        committeeId: $committee->id,
        title: 'Implementation review',
    ));

    $secondReview = $action->create(new CreatePlsReviewData(
        committeeId: $committee->id,
        title: 'Implementation review',
    ));

    expect($firstReview->slug)->toBe('implementation-review')
        ->and($secondReview->slug)->toBe('implementation-review-2');
});

it('validates review creation input before persisting', function () {
    $committee = plsHierarchy()['committee'];

    expect(fn () => app(CreatePlsReview::class)->create(
        new CreatePlsReviewData(
            committeeId: $committee->id,
            title: ' ',
            description: str_repeat('x', 5001),
        ),
    ))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('pls_reviews', 0);
});
