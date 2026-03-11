<?php

use App\Domain\Analysis\Enums\FindingType;
use App\Domain\Analysis\Enums\RecommendationType;
use App\Domain\Analysis\Finding;
use App\Domain\Analysis\Validation\StoreFindingValidator;
use App\Domain\Analysis\Validation\StoreRecommendationValidator;
use App\Domain\Documents\Document;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Validation\StoreDocumentMetadataValidator;
use App\Domain\Institutions\Committee;
use App\Domain\Institutions\Country;
use App\Domain\Institutions\Enums\JurisdictionType;
use App\Domain\Institutions\Enums\LegislatureType;
use App\Domain\Institutions\Jurisdiction;
use App\Domain\Institutions\Legislature;
use App\Domain\Legislation\Enums\LegislationType;
use App\Domain\Legislation\Enums\ReviewLegislationRelationshipType;
use App\Domain\Legislation\Legislation;
use App\Domain\Legislation\Validation\AttachLegislationToReviewValidator;
use App\Domain\Reviews\Actions\CreatePlsReview;
use App\Domain\Reviews\Data\CreatePlsReviewData;
use App\Domain\Reviews\PlsReview;
use Illuminate\Validation\ValidationException;

test('legislation attachment validation prevents duplicate review links', function () {
    $review = makeValidationReview();

    $legislation = Legislation::factory()->create([
        'jurisdiction_id' => $review->jurisdiction_id,
        'legislation_type' => LegislationType::Act,
    ]);

    $review->legislation()->attach($legislation->id, [
        'relationship_type' => ReviewLegislationRelationshipType::Primary,
    ]);

    expect(fn () => app(AttachLegislationToReviewValidator::class)->validate([
        'pls_review_id' => $review->id,
        'legislation_id' => $legislation->id,
        'relationship_type' => ReviewLegislationRelationshipType::Primary->value,
    ]))->toThrow(ValidationException::class);
});

test('document metadata validation rejects invalid type and duplicate storage path', function () {
    $review = makeValidationReview();

    Document::factory()->create([
        'pls_review_id' => $review->id,
        'storage_path' => 'documents/shared-path.pdf',
    ]);

    expect(fn () => app(StoreDocumentMetadataValidator::class)->validate([
        'pls_review_id' => $review->id,
        'title' => 'Implementation report',
        'document_type' => 'unsupported',
        'storage_path' => 'documents/shared-path.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 128_000,
        'summary' => 'Operational update',
        'metadata' => ['source' => 'internal'],
    ]))->toThrow(ValidationException::class);
});

test('finding validation requires a summary or detail and a valid enum value', function () {
    $review = makeValidationReview();

    expect(fn () => app(StoreFindingValidator::class)->validate([
        'pls_review_id' => $review->id,
        'title' => 'Implementation gap',
        'finding_type' => 'unsupported',
        'summary' => null,
        'detail' => null,
    ]))->toThrow(ValidationException::class);
});

test('recommendation validation requires the finding to belong to the selected review', function () {
    $review = makeValidationReview();
    $otherReview = makeValidationReview();

    $finding = Finding::factory()->create([
        'pls_review_id' => $otherReview->id,
        'title' => 'Delayed compliance reporting',
        'finding_type' => FindingType::ComplianceProblem,
    ]);

    expect(fn () => app(StoreRecommendationValidator::class)->validate([
        'pls_review_id' => $review->id,
        'finding_id' => $finding->id,
        'title' => 'Require quarterly reporting',
        'description' => 'Set a fixed reporting calendar for implementing agencies.',
        'recommendation_type' => RecommendationType::ImproveImplementation->value,
    ]))->toThrow(ValidationException::class);
});

function makeValidationReview(): PlsReview
{
    $country = Country::factory()->create([
        'name' => fake()->unique()->country(),
        'iso2' => fake()->unique()->regexify('[A-Z]{2}'),
        'iso3' => fake()->unique()->regexify('[A-Z]{3}'),
    ]);

    $jurisdiction = Jurisdiction::factory()->create([
        'country_id' => $country->id,
        'name' => fake()->unique()->city().' Region',
        'slug' => fake()->unique()->slug(),
        'jurisdiction_type' => JurisdictionType::National,
        'parent_id' => null,
    ]);

    $legislature = Legislature::factory()->create([
        'jurisdiction_id' => $jurisdiction->id,
        'name' => 'National Assembly',
        'slug' => fake()->unique()->slug(),
        'legislature_type' => LegislatureType::Assembly,
    ]);

    $committee = Committee::factory()->create([
        'legislature_id' => $legislature->id,
        'name' => 'Governance and Oversight Committee',
        'slug' => fake()->unique()->slug(),
    ]);

    return app(CreatePlsReview::class)->create(
        CreatePlsReviewData::from([
            'committee_id' => $committee->id,
            'title' => fake()->unique()->sentence(6),
            'description' => fake()->paragraph(),
            'start_date' => '2026-03-10',
        ]),
    );
}
