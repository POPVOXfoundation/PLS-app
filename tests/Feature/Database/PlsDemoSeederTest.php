<?php

use App\Domain\Analysis\Finding;
use App\Domain\Analysis\Recommendation;
use App\Domain\Institutions\Country;
use App\Domain\Institutions\Jurisdiction;
use App\Domain\Institutions\Legislature;
use App\Domain\Institutions\ReviewGroup;
use App\Domain\Legislation\Legislation;
use App\Domain\Reporting\Report;
use App\Domain\Reviews\PlsReview;
use Database\Seeders\PlsDemoSeeder;

it('seeds a realistic pls demo dataset', function () {
    $this->seed(PlsDemoSeeder::class);

    expect(Country::query()->count())->toBeGreaterThanOrEqual(3)
        ->and(Jurisdiction::query()->count())->toBeGreaterThanOrEqual(4)
        ->and(Legislature::query()->count())->toBeGreaterThanOrEqual(4)
        ->and(ReviewGroup::query()->count())->toBeGreaterThanOrEqual(6)
        ->and(PlsReview::query()->count())->toBeGreaterThanOrEqual(3)
        ->and(Legislation::query()->count())->toBeGreaterThanOrEqual(3)
        ->and(Finding::query()->count())->toBeGreaterThanOrEqual(2)
        ->and(Recommendation::query()->count())->toBeGreaterThanOrEqual(2)
        ->and(Report::query()->count())->toBeGreaterThanOrEqual(2);

    expect(
        PlsReview::query()->withCount('steps')->get()->every(
            fn (PlsReview $review): bool => $review->steps_count === 11,
        ),
    )->toBeTrue();

    $this->assertDatabaseHas('countries', ['iso2' => 'BZ']);
    $this->assertDatabaseHas('countries', ['iso2' => 'UG']);
    $this->assertDatabaseHas('countries', ['iso2' => 'US']);
    expect(
        Legislature::query()
            ->whereHas('jurisdiction.country', fn ($query) => $query->where('iso2', 'BZ'))
            ->exists(),
    )->toBeTrue();
    $this->assertDatabaseHas('pls_reviews', ['title' => 'Post-Legislative Review of the Access to Information Act']);
    $this->assertDatabaseHas('reports', ['title' => 'Final Report on the Public Finance Management Act Review']);
});
