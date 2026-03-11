<?php

use App\Domain\Institutions\Committee;
use App\Domain\Institutions\Country;
use App\Domain\Institutions\Enums\JurisdictionType;
use App\Domain\Institutions\Enums\LegislatureType;
use App\Domain\Institutions\Jurisdiction;
use App\Domain\Institutions\Legislature;
use App\Domain\Reviews\Actions\CreatePlsReview;
use App\Domain\Reviews\Data\CreatePlsReviewData;
use App\Domain\Reviews\PlsReview;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

pest()->extend(Tests\TestCase::class)
    ->in('Unit/Domain');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * @param  array{
 *     country?: array<string, mixed>,
 *     jurisdiction?: array<string, mixed>,
 *     legislature?: array<string, mixed>,
 *     committee?: array<string, mixed>
 * }  $overrides
 * @return array{
 *     country: Country,
 *     jurisdiction: Jurisdiction,
 *     legislature: Legislature,
 *     committee: Committee
 * }
 */
function plsHierarchy(array $overrides = []): array
{
    $country = Country::factory()->create(array_merge([
        'name' => 'Belize',
        'iso2' => fake()->unique()->regexify('[A-Z]{2}'),
        'iso3' => fake()->unique()->regexify('[A-Z]{3}'),
    ], $overrides['country'] ?? []));

    $jurisdiction = Jurisdiction::factory()->create(array_merge([
        'country_id' => $country->id,
        'name' => 'National',
        'slug' => 'national-'.fake()->unique()->word(),
        'jurisdiction_type' => JurisdictionType::National,
        'parent_id' => null,
    ], $overrides['jurisdiction'] ?? []));

    $legislature = Legislature::factory()->create(array_merge([
        'jurisdiction_id' => $jurisdiction->id,
        'name' => 'National Assembly',
        'slug' => 'national-assembly-'.fake()->unique()->word(),
        'legislature_type' => LegislatureType::Assembly,
    ], $overrides['legislature'] ?? []));

    $committee = Committee::factory()->create(array_merge([
        'legislature_id' => $legislature->id,
        'name' => 'Governance and Oversight Committee',
        'slug' => 'governance-and-oversight-committee-'.fake()->unique()->word(),
    ], $overrides['committee'] ?? []));

    return [
        'country' => $country,
        'jurisdiction' => $jurisdiction,
        'legislature' => $legislature,
        'committee' => $committee,
    ];
}

/**
 * @param  array{
 *     title?: string,
 *     description?: string|null,
 *     start_date?: string|null
 * }  $reviewAttributes
 * @param  array{
 *     country?: array<string, mixed>,
 *     jurisdiction?: array<string, mixed>,
 *     legislature?: array<string, mixed>,
 *     committee?: array<string, mixed>
 * }  $hierarchyOverrides
 * @return array{
 *     country: Country,
 *     jurisdiction: Jurisdiction,
 *     legislature: Legislature,
 *     committee: Committee,
 *     review: PlsReview
 * }
 */
function plsReviewContext(array $reviewAttributes = [], array $hierarchyOverrides = []): array
{
    $hierarchy = plsHierarchy($hierarchyOverrides);

    $review = app(CreatePlsReview::class)->create(CreatePlsReviewData::from(array_merge([
        'committee_id' => $hierarchy['committee']->id,
        'title' => 'Default PLS Review Title',
        'description' => 'Default review description',
        'start_date' => '2026-03-10',
    ], $reviewAttributes)));

    return [
        ...$hierarchy,
        'review' => $review,
    ];
}

/**
 * @param  array{
 *     title?: string,
 *     description?: string|null,
 *     start_date?: string|null
 * }  $reviewAttributes
 * @param  array{
 *     country?: array<string, mixed>,
 *     jurisdiction?: array<string, mixed>,
 *     legislature?: array<string, mixed>,
 *     committee?: array<string, mixed>
 * }  $hierarchyOverrides
 */
function plsReview(array $reviewAttributes = [], array $hierarchyOverrides = []): PlsReview
{
    return plsReviewContext($reviewAttributes, $hierarchyOverrides)['review'];
}
