<?php

use App\Domain\Institutions\Enums\ReviewGroupType;
use App\Domain\Institutions\ReviewGroup;
use App\Domain\Reviews\Actions\CreatePlsReview;
use App\Domain\Reviews\Data\CreatePlsReviewData;
use App\Domain\Reviews\Enums\PlsReviewMembershipRole;
use App\Domain\Reviews\Enums\PlsReviewStatus;
use App\Domain\Reviews\PlsReviewMembership;
use App\Domain\Reviews\Support\PlsReviewWorkflow;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

it('creates a draft review without a review group and uses the creator as the owner', function () {
    ['country' => $country, 'jurisdiction' => $jurisdiction, 'legislature' => $legislature] = plsHierarchy([
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
    ]);
    $owner = User::factory()->reviewer()->create([
        'country_id' => $country->id,
    ]);

    $review = app(CreatePlsReview::class)->create(
        CreatePlsReviewData::from([
            'legislature_id' => $legislature->id,
            'title' => 'Review of the Access to Information Act',
            'description' => 'Assess implementation outcomes after enactment.',
            'start_date' => '2026-03-10',
            'created_by' => $owner->id,
        ]),
    );

    expect($review->status)->toBe(PlsReviewStatus::Draft)
        ->and($review->current_step_number)->toBe(1)
        ->and($review->review_group_id)->toBeNull()
        ->and($review->created_by)->toBe($owner->id)
        ->and($review->legislature_id)->toBe($legislature->id)
        ->and($review->jurisdiction_id)->toBe($jurisdiction->id)
        ->and($review->country_id)->toBe($country->id)
        ->and($review->slug)->toBe('review-of-the-access-to-information-act')
        ->and($review->start_date?->toDateString())->toBe('2026-03-10');

    $this->assertDatabaseHas('pls_reviews', [
        'id' => $review->id,
        'review_group_id' => null,
        'created_by' => $owner->id,
        'legislature_id' => $legislature->id,
        'jurisdiction_id' => $jurisdiction->id,
        'country_id' => $country->id,
        'status' => PlsReviewStatus::Draft->value,
        'current_step_number' => 1,
    ]);

    $this->assertDatabaseHas('pls_review_memberships', [
        'pls_review_id' => $review->id,
        'user_id' => $owner->id,
        'role' => PlsReviewMembershipRole::Owner->value,
    ]);
});

it('creates a draft review with a review group and the derived institutional hierarchy', function () {
    ['country' => $country, 'jurisdiction' => $jurisdiction, 'legislature' => $legislature] = plsHierarchy();
    $owner = User::factory()->reviewer()->create([
        'country_id' => $country->id,
    ]);
    $reviewGroup = ReviewGroup::factory()->create([
        'country_id' => $country->id,
        'jurisdiction_id' => $jurisdiction->id,
        'legislature_id' => $legislature->id,
        'name' => 'Governance and Oversight Office',
        'type' => ReviewGroupType::Committee,
    ]);

    $review = app(CreatePlsReview::class)->create(new CreatePlsReviewData(
        legislatureId: $legislature->id,
        reviewGroupId: $reviewGroup->id,
        title: 'Review of delegated legislation oversight',
        description: 'Review-group scoped review',
        startDate: CarbonImmutable::parse('2026-03-11'),
        createdBy: $owner->id,
    ));

    expect($review->review_group_id)->toBe($reviewGroup->id)
        ->and($review->created_by)->toBe($owner->id)
        ->and($review->legislature_id)->toBe($legislature->id)
        ->and($review->jurisdiction_id)->toBe($jurisdiction->id)
        ->and($review->country_id)->toBe($country->id);
});

it('seeds the official workflow steps in build plan order', function () {
    ['country' => $country, 'legislature' => $legislature] = plsHierarchy();
    $owner = User::factory()->reviewer()->create([
        'country_id' => $country->id,
    ]);

    $review = app(CreatePlsReview::class)->create(new CreatePlsReviewData(
        legislatureId: $legislature->id,
        reviewGroupId: null,
        title: 'Review of delegated legislation oversight',
        description: null,
        startDate: CarbonImmutable::parse('2026-03-11'),
        createdBy: $owner->id,
    ));

    $steps = $review->steps()->get();
    $expectedDefinitions = PlsReviewWorkflow::definitions();

    expect($steps)->toHaveCount(11)
        ->and($steps->pluck('step_number')->all())->toBe(array_column($expectedDefinitions, 'number'))
        ->and($steps->pluck('step_key')->all())->toBe(array_column($expectedDefinitions, 'key'))
        ->and($steps->map(fn ($step) => $step->title)->all())->toBe(array_column($expectedDefinitions, 'title'))
        ->and($steps->pluck('status')->map(fn ($status) => $status->value)->all())->toBe(array_fill(0, 11, 'pending'));
});

it('creates unique slugs for reviews with the same title for the same owner', function () {
    ['country' => $country, 'legislature' => $legislature] = plsHierarchy();
    $owner = User::factory()->reviewer()->create([
        'country_id' => $country->id,
    ]);

    $action = app(CreatePlsReview::class);

    $firstReview = $action->create(new CreatePlsReviewData(
        legislatureId: $legislature->id,
        reviewGroupId: null,
        title: 'Implementation review',
        createdBy: $owner->id,
    ));

    $secondReview = $action->create(new CreatePlsReviewData(
        legislatureId: $legislature->id,
        reviewGroupId: null,
        title: 'Implementation review',
        createdBy: $owner->id,
    ));

    expect($firstReview->slug)->toBe('implementation-review')
        ->and($secondReview->slug)->toBe('implementation-review-2');
});

it('prevents a second owner membership from being added to a review', function () {
    $review = plsReview();
    $anotherReviewer = User::factory()->reviewer()->create();

    expect(fn () => $review->memberships()->create([
        'user_id' => $anotherReviewer->id,
        'role' => PlsReviewMembershipRole::Owner,
        'invited_by' => $review->created_by,
    ]))->toThrow(ValidationException::class);

    expect($review->fresh()->owner?->id)->toBe($review->created_by)
        ->and(
            PlsReviewMembership::query()
                ->where('pls_review_id', $review->id)
                ->where('role', PlsReviewMembershipRole::Owner->value)
                ->count(),
        )->toBe(1);
});

it('prevents the owner membership from being removed or demoted', function () {
    $review = plsReview();
    $ownerMembership = $review->memberships()->where('role', PlsReviewMembershipRole::Owner->value)->sole();

    expect(function () use ($ownerMembership): void {
        $ownerMembership->update([
            'role' => PlsReviewMembershipRole::Contributor,
        ]);
    })->toThrow(ValidationException::class);

    expect(fn () => $ownerMembership->delete())->toThrow(ValidationException::class);

    expect($review->fresh()->owner?->id)->toBe($review->created_by);
});

it('validates review creation input before persisting', function () {
    ['country' => $country, 'legislature' => $legislature] = plsHierarchy();
    $owner = User::factory()->reviewer()->create([
        'country_id' => $country->id,
    ]);

    expect(fn () => app(CreatePlsReview::class)->create(
        new CreatePlsReviewData(
            legislatureId: $legislature->id,
            reviewGroupId: null,
            title: ' ',
            description: str_repeat('x', 5001),
            createdBy: $owner->id,
        ),
    ))->toThrow(ValidationException::class);

    $this->assertDatabaseCount('pls_reviews', 0);
});

it('prevents creating a review outside the creators country scope', function () {
    ['legislature' => $legislature] = plsHierarchy();
    $otherCountry = \App\Domain\Institutions\Country::factory()->create();
    $owner = User::factory()->reviewer()->create([
        'country_id' => $otherCountry->id,
    ]);

    expect(fn () => app(CreatePlsReview::class)->create(
        CreatePlsReviewData::from([
            'legislature_id' => $legislature->id,
            'title' => 'Cross-country review',
            'created_by' => $owner->id,
        ]),
    ))->toThrow(ValidationException::class);
});
