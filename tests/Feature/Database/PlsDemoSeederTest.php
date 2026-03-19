<?php

use App\Domain\Reviews\PlsReview;
use Database\Seeders\PlsDemoSeeder;

it('seeds a realistic pls demo dataset', function () {
    $this->seed(PlsDemoSeeder::class);

    expect(\App\Domain\Institutions\Country::query()->count())->toBeGreaterThanOrEqual(3)
        ->and(\App\Domain\Institutions\Jurisdiction::query()->count())->toBeGreaterThanOrEqual(4)
        ->and(\App\Domain\Institutions\Legislature::query()->count())->toBeGreaterThanOrEqual(4)
        ->and(\App\Domain\Institutions\ReviewGroup::query()->count())->toBeGreaterThanOrEqual(6)
        ->and(PlsReview::query()->count())->toBeGreaterThanOrEqual(3)
        ->and(\App\Domain\Legislation\Legislation::query()->count())->toBeGreaterThanOrEqual(3)
        ->and(\App\Domain\Analysis\Finding::query()->count())->toBeGreaterThanOrEqual(2)
        ->and(\App\Domain\Analysis\Recommendation::query()->count())->toBeGreaterThanOrEqual(2)
        ->and(\App\Domain\Reporting\Report::query()->count())->toBeGreaterThanOrEqual(2);

    expect(
        PlsReview::query()->withCount('steps')->get()->every(
            fn (PlsReview $review): bool => $review->steps_count === 11,
        ),
    )->toBeTrue();

    $this->assertDatabaseHas('countries', ['iso2' => 'BZ']);
    $this->assertDatabaseHas('countries', ['iso2' => 'UG']);
    $this->assertDatabaseHas('countries', ['iso2' => 'US']);
    $this->assertDatabaseHas('pls_reviews', ['title' => 'Post-Legislative Review of the Access to Information Act']);
    $this->assertDatabaseHas('reports', ['title' => 'Final Report on the Public Finance Management Act Review']);
});
